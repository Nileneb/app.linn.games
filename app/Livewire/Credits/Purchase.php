<?php

namespace App\Livewire\Credits;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Purchase extends Component
{
    public int $selected = 1;

    public function packages(): array
    {
        return config('services.stripe.packages', []);
    }

    public function render(): View
    {
        $workspace = Auth::user()->workspaces()->first();

        return view('livewire.credits.purchase', [
            'workspace' => $workspace,
            'packages' => $this->packages(),
        ])->layout('components.layouts.app');
    }
}
