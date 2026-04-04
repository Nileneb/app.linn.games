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

    public int $tries   = 3;
    public int $timeout = 60;
    public int $backoff = 30;

    public function __construct(private readonly string $trefferId) {}

    public function handle(): void
    {
        $treffer = P5Treffer::find($this->trefferId);

        if ($treffer === null || blank($treffer->doi)) {
            return;
        }

        $unpaywallService = app(UnpaywallService::class);
        $pdfUrl = $unpaywallService->resolveOaUrl($treffer->doi);

        if ($pdfUrl === null) {
            $treffer->update([
                'retrieval_status'        => 'nicht_verfuegbar',
                'retrieval_checked_at'    => now(),
                'retrieval_last_response' => 'Kein Open-Access-Volltext gefunden (Unpaywall).',
            ]);

            return;
        }

        $response = Http::timeout(30)->get($pdfUrl);

        if ($response->failed()) {
            $treffer->update([
                'retrieval_status'        => 'fehler',
                'retrieval_checked_at'    => now(),
                'retrieval_last_response' => "HTTP {$response->status()} beim Download von {$pdfUrl}.",
            ]);

            return;
        }

        $path = 'papers/' . $treffer->projekt_id . '/' . Str::slug($treffer->record_id) . '.pdf';
        Storage::put($path, $response->body());

        $treffer->update([
            'retrieval_downloaded'    => true,
            'retrieval_source_url'    => $pdfUrl,
            'retrieval_storage_path'  => $path,
            'retrieval_status'        => 'heruntergeladen',
            'retrieval_checked_at'    => now(),
            'retrieval_last_response' => null,
        ]);

        // Extract text using dedicated service
        $parserService = app(PdfParserService::class);
        $text = $parserService->extractText($response->body());

        if (blank($text)) {
            Log::warning('PDF text extraction returned empty', [
                'treffer_id' => $this->trefferId,
                'path'       => $path,
                'doi'        => $treffer->doi,
            ]);

            $treffer->update([
                'retrieval_status'        => 'text_extraktion_fehlgeschlagen',
                'retrieval_last_response' => 'PDF heruntergeladen, aber Textextraktion lieferte kein Ergebnis.',
            ]);

            return;
        }

        IngestPaperJob::dispatch(
            paperId:   $treffer->record_id,
            source:    $treffer->datenbank_quelle ?? 'retrieval',
            title:     $treffer->titel ?? '',
            text:      $text,
            projektId: $treffer->projekt_id,
            metadata:  ['doi' => $treffer->doi, 'treffer_id' => $treffer->id],
        );
    }
}
