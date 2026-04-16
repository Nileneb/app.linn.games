<?php

namespace App\Livewire\Billing;

use App\Services\StripeService;
use Illuminate\View\View;
use Livewire\Component;

class MayringSubscription extends Component
{
    public bool $showSuccess = false;

    public string $newTokenName = '';

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

    public function createToken(): void
    {
        $name = trim($this->newTokenName) ?: 'MayringCoder '.now()->format('Y-m-d H:i');
        $token = auth()->user()->createToken($name, ['mayring:access']);

        session()->flash('mcp_token', $token->plainTextToken);
        $this->newTokenName = '';
        $this->dispatch('notify', type: 'success', message: "Token \"{$name}\" erstellt.");
    }

    public function deleteToken(int $tokenId): void
    {
        auth()->user()->tokens()->where('id', $tokenId)->delete();
        $this->dispatch('notify', type: 'warning', message: 'Token gelöscht.');
    }

    public function render(): View
    {
        $workspace = auth()->user()->currentWorkspace();
        $tokens = auth()->user()->tokens()
            ->where('abilities', 'like', '%mayring:access%')
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.billing.mayring-subscription', [
            'workspace' => $workspace,
            'tokens' => $tokens,
            'mcpToken' => session('mcp_token'),
        ]);
    }
}
