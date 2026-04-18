<?php

namespace App\Actions;

use App\Models\Recherche\Projekt;
use App\Services\ProjectExportService;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

/**
 * ExportProjectAction
 * Action zum Exportieren eines Projekts in verschiedene Formate
 */
class ExportProjectAction
{
    public function __construct(
        private readonly ProjectExportService $exportService,
    ) {}

    /**
     * Exportiert Projekt als Markdown (Download)
     */
    public function asMarkdown(Projekt $projekt): \Illuminate\Http\Response
    {
        $markdown = $this->exportService->generateMarkdown($projekt);
        $filename = $this->buildFilename($projekt, 'md');

        return Response::make($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Exportiert Projekt als LaTeX (Download)
     */
    public function asLaTeX(Projekt $projekt, string $style = 'generic'): \Illuminate\Http\Response
    {
        $latex = $this->exportService->generateLaTeX($projekt, $style);
        $filename = Str::slug($projekt->titel ?? 'review').'-'.$style.'.tex';

        return Response::make($latex, 200, [
            'Content-Type' => 'application/x-latex',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Gibt Markdown-Inhalts als String (z.B. zum Anzeigen)
     */
    public function getMarkdown(Projekt $projekt): string
    {
        return $this->exportService->generateMarkdown($projekt);
    }

    /**
     * Gibt LaTeX-Inhalts als String
     */
    public function getLaTeX(Projekt $projekt, string $style = 'generic'): string
    {
        return $this->exportService->generateLaTeX($projekt, $style);
    }

    private function buildFilename(Projekt $projekt, string $format): string
    {
        $title = Str::slug($projekt->titel ?? 'systematic-review');
        $date = now()->format('Y-m-d');

        return "{$title}_{$date}.{$format}";
    }
}
