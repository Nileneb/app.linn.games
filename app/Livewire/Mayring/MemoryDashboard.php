<?php

namespace App\Livewire\Mayring;

use App\Services\MayringMcpClient;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MemoryDashboard extends Component
{
    public array $stats = [];
    public string $error = '';

    public function mount(): void
    {
        $this->fetchStats();
    }

    public function fetchStats(): void
    {
        $this->error = '';

        try {
            $data = app(MayringMcpClient::class)->getStats();
            $this->stats = $data;
        } catch (\Throwable $e) {
            $this->error = 'Verbindung fehlgeschlagen: '.$e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.mayring.memory-dashboard');
    }
}
