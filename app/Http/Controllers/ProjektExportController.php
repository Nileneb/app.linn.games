<?php

namespace App\Http\Controllers;

use App\Actions\ExportProjectAction;
use App\Models\Recherche\Projekt;
use App\Services\MayringSnippetService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjektExportController extends Controller
{
    public function __construct(
        private readonly ExportProjectAction $exportAction,
        private readonly MayringSnippetService $mayringService,
    ) {}

    /**
     * Exportiert Projekt als Markdown
     */
    public function exportMarkdown(Request $request, Projekt $projekt): Response
    {
        $this->authorize('view', $projekt);

        return $this->exportAction->asMarkdown($projekt);
    }

    /**
     * Exportiert Projekt als LaTeX
     */
    public function exportLaTeX(Request $request, Projekt $projekt): Response
    {
        $this->authorize('view', $projekt);

        return $this->exportAction->asLaTeX($projekt);
    }

    /**
     * Exportiert Mayring-Snippets als Markdown
     */
    public function exportMayringMarkdown(Request $request, Projekt $projekt): Response
    {
        $this->authorize('view', $projekt);

        $markdown = $this->mayringService->exportAsMarkdown($projekt->id);

        return response($markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"mayring-{$projekt->id}-" . now()->format('Y-m-d-His') . ".md\"",
        ]);
    }

    /**
     * Gibt Mayring-Statistiken als JSON zurück
     */
    public function mayringStats(Request $request, Projekt $projekt)
    {
        $this->authorize('view', $projekt);

        $stats = $this->mayringService->getStats($projekt->id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
