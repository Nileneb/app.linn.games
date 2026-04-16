<?php

namespace App\Jobs;

use App\Models\Recherche\P5Treffer;
use App\Services\PdfParserService;
use App\Services\UnpaywallService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadPaperJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $backoff = 30;

    public function __construct(private readonly string $trefferId) {}

    public function handle(): void
    {
        $treffer = P5Treffer::find($this->trefferId);

        if ($treffer === null || blank($treffer->doi)) {
            return;
        }

        // 1. Try full PDF via Unpaywall
        $text = $this->tryUnpaywallPdf($treffer);

        if ($text !== null) {
            $this->dispatchIngest($treffer, $text, 'fulltext');

            return;
        }

        // 2. Fallback: use abstract (even if PDF was downloaded but text extraction failed)
        $treffer->refresh();
        if (! blank($treffer->abstract)) {
            $wasDownloaded = (bool) $treffer->retrieval_downloaded;
            $treffer->update([
                'retrieval_status' => $wasDownloaded ? 'text_extraktion_fehlgeschlagen' : 'abstract_only',
                'retrieval_checked_at' => now(),
                'retrieval_last_response' => $wasDownloaded
                    ? 'PDF heruntergeladen, Textextraktion fehlgeschlagen — Abstract wird für Analyse verwendet.'
                    : 'Volltext nicht verfügbar — Abstract wird für Analyse verwendet.',
            ]);

            $this->dispatchIngest($treffer, $treffer->abstract, 'abstract');

            return;
        }

        // 3. Neither fulltext nor abstract → keep current status or set bibliothek_erforderlich
        $fresh = P5Treffer::find($this->trefferId);
        if ($fresh && $fresh->retrieval_status !== 'text_extraktion_fehlgeschlagen') {
            $treffer->update([
                'retrieval_status' => 'bibliothek_erforderlich',
                'retrieval_checked_at' => now(),
                'retrieval_last_response' => 'Weder Volltext noch Abstract verfügbar. Bitte manuell über Bibliothek beschaffen.',
            ]);
        }
    }

    private function tryUnpaywallPdf(P5Treffer $treffer): ?string
    {
        $pdfUrl = app(UnpaywallService::class)->resolveOaUrl($treffer->doi);

        if ($pdfUrl === null) {
            return null;
        }

        $response = Http::timeout(30)->get($pdfUrl);

        if ($response->failed()) {
            Log::info('PDF download failed', ['doi' => $treffer->doi, 'status' => $response->status()]);

            return null;
        }

        $path = 'papers/'.$treffer->projekt_id.'/'.Str::slug($treffer->record_id).'.pdf';
        Storage::put($path, $response->body());

        $treffer->update([
            'retrieval_downloaded' => true,
            'retrieval_source_url' => $pdfUrl,
            'retrieval_storage_path' => $path,
            'retrieval_status' => 'heruntergeladen',
            'retrieval_checked_at' => now(),
            'retrieval_last_response' => null,
        ]);

        $text = app(PdfParserService::class)->extractText($response->body());

        if (blank($text)) {
            $treffer->update([
                'retrieval_status' => 'text_extraktion_fehlgeschlagen',
                'retrieval_last_response' => 'PDF heruntergeladen, aber Textextraktion lieferte kein Ergebnis.',
            ]);

            return null;
        }

        return $text;
    }

    private function dispatchIngest(P5Treffer $treffer, string $text, string $source): void
    {
        IngestPaperJob::dispatch(
            paperId: $treffer->record_id,
            source: $treffer->datenbank_quelle ?? 'retrieval',
            title: $treffer->titel ?? '',
            text: $text,
            projektId: $treffer->projekt_id,
            metadata: [
                'doi' => $treffer->doi,
                'treffer_id' => $treffer->id,
                'retrieval_type' => $source,
            ],
        );
    }
}
