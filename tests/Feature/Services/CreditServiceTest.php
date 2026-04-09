<?php

use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AgentDailyLimitExceededException;
use App\Services\CreditService;
use App\Services\InsufficientCreditsException;
use Illuminate\Support\Carbon;

function makeWorkspace(int $balanceCents = 0): Workspace
{
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace = Workspace::create([
        'owner_id' => $user->id,
        'name' => $user->name.' Workspace',
    ]);
    \App\Models\WorkspaceUser::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
    if ($balanceCents > 0) {
        app(CreditService::class)->topUp($workspace, $balanceCents);
    }

    return $workspace->fresh();
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
    config(['services.anthropic.price_per_1k_input_tokens_cents' => 2]);
    $service = app(CreditService::class);

    expect($service->toCents(1000))->toBe(2);
    expect($service->toCents(500))->toBe(1);
    expect($service->toCents(1))->toBe(1);   // ceil → mindestens 1 Cent
    expect($service->toCents(2000))->toBe(4);
});

test('deduct wirft exception wenn guthaben nicht ausreicht', function () {
    $workspace = makeWorkspace(10); // 10 Cent Guthaben

    expect(fn () => app(CreditService::class)->deduct($workspace->fresh(), 100000, 'search_agent'))
        ->toThrow(InsufficientCreditsException::class);

    // Kein Guthaben wurde abgezogen, keine Usage-Transaktion erstellt
    expect($workspace->fresh()->credits_balance_cents)->toBe(10);
    expect(CreditTransaction::where('workspace_id', $workspace->id)->where('type', 'usage')->count())->toBe(0);
});

test('deduct mit 0 tokens erstellt keine transaktion', function () {
    $workspace = makeWorkspace(1000);

    app(CreditService::class)->deduct($workspace->fresh(), 0, 'search_agent');

    expect($workspace->fresh()->credits_balance_cents)->toBe(1000);
    expect(CreditTransaction::where('workspace_id', $workspace->id)->where('type', 'usage')->count())->toBe(0);
});

// ─── Low-Balance-Warnung ───────────────────────────────────────────

test('checkLowBalance gibt true zurück wenn guthaben unter schwellenwert', function () {
    config([
        'services.langdock.low_balance_threshold_percent' => 10,
        'services.anthropic.price_per_1k_input_tokens_cents' => 2,
    ]);
    $workspace = makeWorkspace(1000);

    // Verbrauche 950 von 1000 → 5% übrig (475000 tokens * 2 Cent/1k = 950 Cent)
    app(CreditService::class)->deduct($workspace->fresh(), 475000, 'test_agent');

    $result = app(CreditService::class)->checkLowBalance($workspace->fresh());
    expect($result)->toBeTrue();
});

test('checkLowBalance gibt false zurück wenn guthaben über schwellenwert', function () {
    config([
        'services.langdock.low_balance_threshold_percent' => 10,
        'services.anthropic.price_per_1k_input_tokens_cents' => 2,
    ]);
    $workspace = makeWorkspace(1000);

    // Verbrauche nur 100 von 1000 → 90% übrig (50000 tokens * 2 Cent/1k = 100 Cent)
    app(CreditService::class)->deduct($workspace->fresh(), 50000, 'test_agent');

    $result = app(CreditService::class)->checkLowBalance($workspace->fresh());
    expect($result)->toBeFalse();
});

test('checkLowBalance gibt false zurück wenn kein topup vorhanden', function () {
    $workspace = makeWorkspace();
    $result = app(CreditService::class)->checkLowBalance($workspace);
    expect($result)->toBeFalse();
});

// ─── Tageslimit pro Agent ──────────────────────────────────────────

test('assertAgentDailyLimit wirft exception wenn tageslimit überschritten', function () {
    config([
        'services.anthropic.agent_daily_limits.test_agent' => 100,
        'services.anthropic.price_per_1k_input_tokens_cents' => 2,
    ]);
    $workspace = makeWorkspace(10000);

    // Erste Nutzung: 80 cents → OK (40000 tokens * 2 Cent/1k = 80 Cent)
    app(CreditService::class)->deduct($workspace->fresh(), 40000, 'test_agent');

    // Zweite Nutzung: nochmal 80 cents → 160 total > 100 Limit
    expect(fn () => app(CreditService::class)->deduct($workspace->fresh(), 40000, 'test_agent'))
        ->toThrow(AgentDailyLimitExceededException::class);
});

test('assertAgentDailyLimit erlaubt nutzung wenn kein limit konfiguriert', function () {
    config(['services.anthropic.agent_daily_limits.unlimited_agent' => 0]);
    $workspace = makeWorkspace(10000);

    // Kein Limit → sollte durchgehen
    app(CreditService::class)->deduct($workspace->fresh(), 100000, 'unlimited_agent');

    expect($workspace->fresh()->credits_balance_cents)->toBeLessThan(10000);
});

test('assertAgentDailyLimit zählt nur heutige ausgaben', function () {
    config(['services.anthropic.agent_daily_limits.daily_agent' => 500]);
    $workspace = makeWorkspace(10000);

    // Gestern: 400 cents ausgegeben
    CreditTransaction::create([
        'workspace_id' => $workspace->id,
        'type' => 'usage',
        'amount_cents' => -400,
        'tokens_used' => 200000,
        'agent_config_key' => 'daily_agent',
        'created_at' => Carbon::yesterday(),
    ]);

    // Heute: 80 cents → innerhalb des Limits da gestern nicht zählt
    expect(fn () => app(CreditService::class)->deduct($workspace->fresh(), 40000, 'daily_agent'))
        ->not->toThrow(AgentDailyLimitExceededException::class);
});

// ─── Verbrauchszusammenfassung ─────────────────────────────────────

test('usageSummary liefert verbrauch pro agent', function () {
    $workspace = makeWorkspace(10000);
    $service = app(CreditService::class);

    $service->deduct($workspace->fresh(), 5000, 'search_agent');
    $service->deduct($workspace->fresh(), 3000, 'search_agent');
    $service->deduct($workspace->fresh(), 2000, 'review_agent');

    $summary = $service->usageSummary($workspace);

    expect($summary)->toHaveCount(2);

    $searchSummary = collect($summary)->firstWhere('agent_config_key', 'search_agent');
    expect($searchSummary)->not->toBeNull();
    expect($searchSummary['request_count'])->toBe(2);
    expect($searchSummary['total_tokens'])->toBe(8000);

    $reviewSummary = collect($summary)->firstWhere('agent_config_key', 'review_agent');
    expect($reviewSummary)->not->toBeNull();
    expect($reviewSummary['request_count'])->toBe(1);
    expect($reviewSummary['total_tokens'])->toBe(2000);
});

test('usageSummary filtert nach zeitraum', function () {
    $workspace = makeWorkspace(10000);
    $service = app(CreditService::class);

    // Aktuelle Nutzung
    $service->deduct($workspace->fresh(), 5000, 'search_agent');

    // Alte Transaktion (vor 60 Tagen) — direkt in DB
    DB::table('credit_transactions')->insert([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'workspace_id' => $workspace->id,
        'type' => 'usage',
        'amount_cents' => -50,
        'tokens_used' => 25000,
        'agent_config_key' => 'old_agent',
        'created_at' => Carbon::now()->subDays(60),
    ]);

    // Standard: letzte 30 Tage → nur search_agent
    $summary = $service->usageSummary($workspace);
    $agents = collect($summary)->pluck('agent_config_key')->all();
    expect($agents)->toContain('search_agent');
    expect($agents)->not->toContain('old_agent');

    // Letzte 90 Tage → beide Agents
    $summary = $service->usageSummary($workspace, Carbon::now()->subDays(90));
    $agents = collect($summary)->pluck('agent_config_key')->all();
    expect($agents)->toContain('search_agent');
    expect($agents)->toContain('old_agent');
});

test('usageSummary gibt leeres array zurück wenn keine nutzung', function () {
    $workspace = makeWorkspace(1000);
    $summary = app(CreditService::class)->usageSummary($workspace);
    expect($summary)->toBe([]);
});

// ─── Input/Output Pricing ──────────────────────────────────────────

test('toCents berechnet input und output separat', function () {
    config([
        'services.anthropic.price_per_1k_input_tokens_cents' => 1,
        'services.anthropic.price_per_1k_output_tokens_cents' => 4,
    ]);
    $service = app(CreditService::class);

    // 1000 input = 1 Cent, 1000 output = 4 Cent → 5 total
    expect($service->toCents(1000, 1000))->toBe(5);
    // 0 output → nur input
    expect($service->toCents(1000, 0))->toBe(1);
    // ceil: 1 token input → 1 Cent minimum
    expect($service->toCents(1, 0))->toBe(1);
});
