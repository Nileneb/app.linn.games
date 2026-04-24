<?php

use App\Livewire\Mayring\MemoryDashboard;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Config::set('services.mayring_mcp.endpoint', 'http://fake-mayring:8090');
});

test('requires authentication', function () {
    $this->get(route('mayring.stats'))
        ->assertRedirect(route('login'));
});

test('renders dashboard for active subscriber', function () {
    Http::fake(['fake-mayring:8090/stats/summary' => Http::response([
        'chunks'     => ['active' => 42, 'total' => 50],
        'sources'    => ['count' => 7],
        'feedback'   => ['positive' => 10, 'negative' => 2, 'neutral' => 0],
        'ingestion'  => ['last_hour' => 3, 'last_24h' => 15],
        'recent_ops' => [],
    ])]);

    $user = User::factory()->withoutTwoFactor()->create();
    $user->currentWorkspace()->update(['mayring_active' => true]);

    Livewire::actingAs($user)
        ->test(MemoryDashboard::class)
        ->assertSee('Memory Dashboard')
        ->assertSee('42')
        ->assertSee('7')
        ->assertSee('83%');
});

test('shows error message when api is unavailable', function () {
    Http::fake(['fake-mayring:8090/stats/summary' => Http::response(null, 503)]);

    $user = User::factory()->withoutTwoFactor()->create();
    $user->currentWorkspace()->update(['mayring_active' => true]);

    Livewire::actingAs($user)
        ->test(MemoryDashboard::class)
        ->assertSee('Verbindung fehlgeschlagen');
});

test('renders recent_ops in operations log', function () {
    Http::fake(['fake-mayring:8090/stats/summary' => Http::response([
        'chunks'     => ['active' => 0, 'total' => 0],
        'sources'    => ['count' => 0],
        'feedback'   => ['positive' => 0, 'negative' => 0, 'neutral' => 0],
        'ingestion'  => ['last_hour' => 0, 'last_24h' => 0],
        'recent_ops' => [
            ['event_type' => 'ingest_chunk', 'source_id' => 'repo:test/src/foo.py', 'created_at' => now()->toIso8601String()],
        ],
    ])]);

    $user = User::factory()->withoutTwoFactor()->create();
    $user->currentWorkspace()->update(['mayring_active' => true]);

    Livewire::actingAs($user)
        ->test(MemoryDashboard::class)
        ->assertSee('ingest_chunk')
        ->assertSee('repo:test/src/foo.py');
});

test('blocks users without active mayring subscription', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $user->currentWorkspace()->update(['mayring_active' => false]);

    $this->actingAs($user)
        ->get(route('mayring.stats'))
        ->assertRedirect();
});
