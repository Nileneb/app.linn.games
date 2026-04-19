<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function __construct(private readonly CreditPolicy $policy) {}

    public function assertHasBalance(Workspace $workspace): void
    {
        $this->policy->assertHasBalance($workspace);
    }

    /**
     * Zieht Credits ab basierend auf tatsächlichen API-Kosten.
     *
     * @param  float  $actualCostUsd  Tatsächliche API-Kosten in USD (aus CLI total_cost_usd).
     *                                0.0 = Fallback auf Token-basierte Berechnung.
     */
    public function deduct(Workspace $workspace, int $inputTokens, string $agentKey, int $outputTokens = 0, float $actualCostUsd = 0.0): void
    {
        // Bevorzuge echte API-Kosten (inkl. Cache-Writes) über Token-Schätzung
        $rawCents = $actualCostUsd > 0.0
            ? (int) ceil($actualCostUsd * 100)
            : $this->toCents($inputTokens, $outputTokens);

        if ($rawCents <= 0) {
            return;
        }

        $markupFactor = (float) config("services.anthropic.markup_factors.{$agentKey}",
            config('services.anthropic.markup_factors.default', 3.0)
        );
        $discountFactor = (float) ($workspace->discount_factor ?? 1.0);
        $cents = (int) ceil($rawCents * $markupFactor * $discountFactor);

        DB::transaction(function () use ($workspace, $cents, $inputTokens, $outputTokens, $agentKey): void {
            // Lock workspace row first — prevents concurrent deduct race conditions
            $lockedWorkspace = DB::table('workspaces')
                ->where('id', $workspace->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedWorkspace || $lockedWorkspace->credits_balance_cents < $cents) {
                throw new InsufficientCreditsException(
                    'Guthaben aufgebraucht. Bitte den Admin kontaktieren.'
                );
            }

            // Daily limit check inside the lock — prevents concurrent calls both passing the check
            $this->policy->assertAgentDailyLimit($workspace, $agentKey, $cents);

            $workspace->decrement('credits_balance_cents', $cents);
            CreditTransaction::create([
                'workspace_id' => $workspace->id,
                'type' => 'usage',
                'amount_cents' => -$cents,
                'tokens_used' => $inputTokens + $outputTokens,
                'agent_config_key' => $agentKey,
            ]);
        });

        $this->policy->checkLowBalance($workspace->fresh());
    }

    public function topUp(Workspace $workspace, int $cents, string $description = ''): void
    {
        DB::transaction(function () use ($workspace, $cents, $description): void {
            $workspace->increment('credits_balance_cents', $cents);
            CreditTransaction::create([
                'workspace_id' => $workspace->id,
                'type' => 'topup',
                'amount_cents' => $cents,
                'description' => $description ?: null,
            ]);
        });
    }

    public function toCents(int $inputTokens, int $outputTokens = 0): int
    {
        $inputPrice = (float) config('services.anthropic.price_per_1k_input_tokens_cents', 0.08);
        $outputPrice = (float) config('services.anthropic.price_per_1k_output_tokens_cents', 0.40);

        $inputCents = $inputTokens > 0 ? $inputTokens * $inputPrice / 1000 : 0.0;
        $outputCents = $outputTokens > 0 ? $outputTokens * $outputPrice / 1000 : 0.0;

        return (int) ceil($inputCents + $outputCents);
    }

    public function checkLowBalance(Workspace $workspace): bool
    {
        return $this->policy->checkLowBalance($workspace);
    }

    public function assertDailyLimitNotReached(Workspace $workspace, string $agentKey): void
    {
        $this->policy->assertDailyLimitNotReached($workspace, $agentKey);
    }

    public function assertAgentDailyLimit(Workspace $workspace, string $agentKey, int $cents): void
    {
        $this->policy->assertAgentDailyLimit($workspace, $agentKey, $cents);
    }

    /**
     * Liefert die Verbrauchszusammenfassung pro Agent für einen gegebenen Zeitraum.
     *
     * @return array<int, array{agent_config_key: string, total_cents: int, total_tokens: int, request_count: int}>
     */
    public function usageSummary(Workspace $workspace, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= Carbon::now()->subDays(30);
        $to ??= Carbon::now();

        return CreditTransaction::where('workspace_id', $workspace->id)
            ->where('type', 'usage')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_config_key, SUM(ABS(amount_cents)) as total_cents, SUM(tokens_used) as total_tokens, COUNT(*) as request_count')
            ->groupBy('agent_config_key')
            ->orderByDesc('total_cents')
            ->get()
            ->map(fn ($row) => [
                'agent_config_key' => $row->agent_config_key,
                'total_cents' => (int) $row->total_cents,
                'total_tokens' => (int) $row->total_tokens,
                'request_count' => (int) $row->request_count,
            ])
            ->all();
    }

    public function checkCloneLimit(Workspace $workspace): void
    {
        $this->policy->checkCloneLimit($workspace);
    }
}
