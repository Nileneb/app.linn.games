<?php

use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\CreditService;
use App\Services\InsufficientCreditsException;

function makeWorkspace(int $balanceCents = 0): Workspace
{
    $user = User::factory()->withoutTwoFactor()->create();
    return $user->ensureDefaultWorkspace()->fresh();
}

test('topUp erhöht guthaben und erstellt transaktion', function () {
    $workspace = makeWorkspace();
    app(CreditService::class)->topUp($workspace, 500, 'Testaufladung');

    expect($workspace->fresh()->credits_balance_cents)->toBe(500);
    expect(CreditTransaction::where('workspace_id', $workspace->id)->where('type', 'topup')->count())->toBe(1);

    $tx = CreditTransaction::where('workspace_id', $workspace->id)->first();
    expect($tx->amount_cents)->toBe(500);
    expect($tx->description)->toBe('Testaufladung');
});

test('deduct zieht guthaben ab und erstellt transaktion', function () {
    $workspace = makeWorkspace();
    app(CreditService::class)->topUp($workspace, 1000);
    app(CreditService::class)->deduct($workspace->fresh(), 5000, 'search_agent');

    $tx = CreditTransaction::where('workspace_id', $workspace->id)->where('type', 'usage')->first();
    expect($tx)->not->toBeNull();
    expect($tx->amount_cents)->toBeLessThan(0);
    expect($tx->tokens_used)->toBe(5000);
    expect($tx->agent_config_key)->toBe('search_agent');
});

test('assertHasBalance wirft exception wenn guthaben leer', function () {
    $workspace = makeWorkspace();

    expect(fn () => app(CreditService::class)->assertHasBalance($workspace))
        ->toThrow(InsufficientCreditsException::class);
});

test('assertHasBalance wirft keine exception wenn guthaben vorhanden', function () {
    $workspace = makeWorkspace();
    app(CreditService::class)->topUp($workspace, 100);

    expect(fn () => app(CreditService::class)->assertHasBalance($workspace->fresh()))->not->toThrow(InsufficientCreditsException::class);
});

test('toCents berechnet korrekt bei konfigurierbarem preis', function () {
    config(['services.langdock.price_per_1k_tokens_cents' => 2]);
    $service = app(CreditService::class);

    expect($service->toCents(1000))->toBe(2);
    expect($service->toCents(500))->toBe(1);
    expect($service->toCents(1))->toBe(1);   // ceil → mindestens 1 Cent
    expect($service->toCents(2000))->toBe(4);
});

test('topUp und deduct sind atomar bei datenbankfehler', function () {
    $workspace = makeWorkspace();
    $initialBalance = $workspace->credits_balance_cents;

    // topUp sollte atomar sein
    app(CreditService::class)->topUp($workspace, 200, 'atomar');
    $workspace->refresh();

    expect($workspace->credits_balance_cents)->toBe($initialBalance + 200);
    expect(CreditTransaction::where('workspace_id', $workspace->id)->count())->toBe(1);
});
