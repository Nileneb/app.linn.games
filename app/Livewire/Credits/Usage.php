<?php

namespace App\Livewire\Credits;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Usage extends Component
{
    use WithPagination;

    public function render(): View
    {
        $workspace = Auth::user()->workspaces()->first();
        $transactions = $workspace
            ? $workspace->creditTransactions()->latest('created_at')->paginate(25)
            : collect();

        return view('livewire.credits.usage', [
            'workspace' => $workspace,
            'transactions' => $transactions,
        ])->layout('components.layouts.app');
    }
}
