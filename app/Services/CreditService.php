<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditService
{
    public function assertHasBalance(Workspace $workspace): void
    {
        if ($workspace->credits_balance_cents <= 0) {
            throw new InsufficientCreditsException('Guthaben aufgebraucht. Bitte den Admin kontaktieren.');
        }
    }

    public function deduct(Workspace $workspace, int $inputTokens, string $agentKey, int $outputTokens = 0): void
    {
        $cents = $this->toCents($inputTokens, $outputTokens);

        if ($cents <= 0) {
            return;
        }

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
            $this->assertAgentDailyLimit($workspace, $agentKey, $cents);

            $workspace->decrement('credits_balance_cents', $cents);
            CreditTransaction::create([
                'workspace_id' => $workspace->id,
                'type' => 'usage',
                'amount_cents' => -$cents,
                'tokens_used' => $inputTokens + $outputTokens,
                'agent_config_key' => $agentKey,
            ]);
        });

        $this->checkLowBalance($workspace->fresh());
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
        $inputPrice = (int) config('services.anthropic.price_per_1k_input_tokens_cents', 1);
        $outputPrice = (int) config('services.anthropic.price_per_1k_output_tokens_cents', 4);

        $inputCents = $inputTokens > 0 ? (int) ceil($inputTokens * $inputPrice / 1000) : 0;
        $outputCents = $outputTokens > 0 ? (int) ceil($outputTokens * $outputPrice / 1000) : 0;

        return $inputCents + $outputCents;
    }

    /**
     * Prüft ob das Guthaben unter den konfigurierbaren Schwellenwert gefallen ist.
     * Loggt eine Warnung wenn das Restguthaben unter dem Schwellenwert liegt.
     */
    public function checkLowBalance(Workspace $workspace): bool
    {
        $thresholdPercent = (int) config('services.anthropic.low_balance_threshold_percent', 10);

        $totalTopUps = CreditTransaction::where('workspace_id', $workspace->id)
            ->where('type', 'topup')
            ->sum('amount_cents');

        if ($totalTopUps <= 0) {
            return false;
        }

        $currentBalance = $workspace->credits_balance_cents;
        $percentRemaining = (int) round(($currentBalance / $totalTopUps) * 100);

        if ($percentRemaining <= $thresholdPercent && $currentBalance > 0) {
            Log::warning('Low credit balance warning', [
                'workspace_id' => $workspace->id,
                'balance_cents' => $currentBalance,
                'percent_remaining' => $percentRemaining,
                'threshold_percent' => $thresholdPercent,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Prüft ob ein Agent sein konfigurierbares Tageslimit überschreiten würde.
     *
     * @throws AgentDailyLimitExceededException
     */
    public function assertAgentDailyLimit(Workspace $workspace, string $agentKey, int $cents): void
    {
        $dailyLimitCents = (int) config("services.anthropic.agent_daily_limits.{$agentKey}", 0);

        if ($dailyLimitCents <= 0) {
            return;
        }

        $spentToday = $this->agentSpendingToday($workspace, $agentKey);

        if (($spentToday + $cents) > $dailyLimitCents) {
            Log::warning('Agent daily limit exceeded', [
                'workspace_id' => $workspace->id,
                'agent_key' => $agentKey,
                'spent_today' => $spentToday,
                'requested_cents' => $cents,
                'daily_limit' => $dailyLimitCents,
            ]);

            throw new AgentDailyLimitExceededException(
                "Tageslimit für Agent '{$agentKey}' erreicht ({$spentToday}/{$dailyLimitCents} Cents)."
            );
        }
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

    /**
     * Berechnet die heutigen Ausgaben eines Agents für einen Workspace.
     */
    private function agentSpendingToday(Workspace $workspace, string $agentKey): int
    {
        return (int) CreditTransaction::where('workspace_id', $workspace->id)
            ->where('type', 'usage')
            ->where('agent_config_key', $agentKey)
            ->where('created_at', '>=', Carbon::today())
            ->sum(DB::raw('ABS(amount_cents)'));
    }
}
