<?php

namespace App\Livewire\Mayring;

use App\Services\JwtIssuer;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Setup-Seite für den lokalen Conversation-Watcher.
 *
 * Der User klickt "Token generieren" → wir erstellen einen 30-Tage-JWT
 * mit scope=watcher, geben ihn einmalig aus und rendern die fertige
 * Docker-Compose-Command-Zeile. Der Token landet NICHT in der Session
 * gespeichert — wer die Seite verlässt und zurückkommt, muss erneut
 * generieren (keine versehentliche Dauersichtbarkeit).
 */
#[Layout('components.layouts.app')]
class WatcherSetup extends Component
{
    public ?string $generatedToken = null;

    public ?string $expiresAt = null;

    public string $apiBaseUrl = '';

    public function mount(): void
    {
        // mcp.linn.games ist der öffentliche Endpoint der mayring-api hinter
        // nginx. Für Dev kann der User es im UI überschreiben.
        $this->apiBaseUrl = config('services.mayring.ui_url') !== null
            ? str_replace('/ui', '', (string) config('services.mayring.ui_url'))
            : 'https://mcp.linn.games';
    }

    public function generateToken(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        $workspace = $user->currentWorkspace();
        $this->generatedToken = app(JwtIssuer::class)->issueForWatcher($user, $workspace);

        $ttl = (int) config('services.jwt.watcher_ttl', 30 * 24 * 3600);
        $this->expiresAt = now()->addSeconds($ttl)->toDayDateTimeString();

        $this->dispatch('notify', type: 'success',
            message: 'Watcher-Token erzeugt. Kopier ihn in den Docker-Command unten — er bleibt ~'.(int) round($ttl / 86400).' Tage gültig.'
        );
    }

    public function render(): View
    {
        return view('livewire.mayring.watcher-setup');
    }
}
