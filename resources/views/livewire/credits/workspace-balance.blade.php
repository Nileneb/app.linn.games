<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public function getBalance(): int
    {
        $workspaceId = Auth::user()?->activeWorkspaceId();
        if ($workspaceId === null) {
            return 0;
        }

        return (int) \App\Models\Workspace::where('id', $workspaceId)
            ->value('credits_balance_cents');
    }
}; ?>

@php $balance = $this->getBalance(); @endphp

<div class="flex items-center gap-2 rounded-md px-3 py-2 text-xs {{ $balance <= 0 ? 'text-red-500 dark:text-red-400' : ($balance < 500 ? 'text-amber-500 dark:text-amber-400' : 'text-zinc-500 dark:text-zinc-400') }}">
    <svg class="h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
    </svg>
    <span>{{ number_format($balance / 100, 2, ',', '.') }} €</span>
</div>
