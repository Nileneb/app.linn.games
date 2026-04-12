<?php

namespace App\Livewire\Billing;

use App\Services\StripeService;
use Illuminate\View\View;
use Livewire\Component;

class MayringSubscription extends Component
{
    public bool $showSuccess = false;

    public function mount(): void
    {
        $this->showSuccess = (bool) request('success');
    }

    public function subscribe(): mixed
    {
        $workspace = auth()->user()->currentWorkspace();

        $url = app(StripeService::class)->createMayringSubscriptionCheckout(
            $workspace,
            route('mayring.subscribe').'?success=1',
            route('mayring.subscribe'),
        );

        return redirect($url);
    }

    public function cancel(): void
    {
        $workspace = auth()->user()->currentWorkspace();
        app(StripeService::class)->cancelMayringSubscription($workspace);
        $workspace->update(['mayring_active' => false]);

        $this->dispatch('notify', type: 'warning', message: 'Abo gekündigt. Zugang bleibt bis Ende des Abrechnungszeitraums.');
    }

    public function regenerateToken(): void
    {
        $user = auth()->user();
        // Altes MayringCoder-Token löschen, neues generieren
        $user->tokens()->where('name', 'MayringCoder External')->delete();
        $token = $user->createToken('MayringCoder External', ['mayring:access']);

        session()->flash('mcp_token', $token->plainTextToken);
        $this->dispatch('notify', type: 'success', message: 'Neues API-Token generiert.');
    }

    public function render(): View
    {
        $workspace = auth()->user()->currentWorkspace();
        $hasToken = auth()->user()->tokens()->where('name', 'MayringCoder External')->exists();

        return view('livewire.billing.mayring-subscription', [
            'workspace' => $workspace,
            'hasToken' => $hasToken,
            'mcpToken' => session('mcp_token'),
            'mcpEndpoint' => config('services.mayring_mcp.endpoint', 'https://app.linn.games:8090'),
        ]);
    }
}
