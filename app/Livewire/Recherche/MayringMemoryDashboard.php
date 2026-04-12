<?php

namespace App\Livewire\Recherche;

use App\Services\MayringMcpClient;
use Illuminate\View\View;
use Livewire\Component;

class MayringMemoryDashboard extends Component
{
    public string $searchQuery = '';
    public array $results = [];
    public bool $searching = false;
    public string $error = '';

    public function search(): void
    {
        $this->error = '';
        $this->searching = true;

        try {
            $data = app(MayringMcpClient::class)
                ->searchDocuments($this->searchQuery ?: 'Forschung', [], 20);
            $this->results = $data['results'] ?? [];
        } catch (\Throwable $e) {
            $this->error = 'Suche fehlgeschlagen: '.$e->getMessage();
            $this->results = [];
        }

        $this->searching = false;
    }

    public function render(): View
    {
        return view('livewire.recherche.mayring-memory-dashboard');
    }
}
