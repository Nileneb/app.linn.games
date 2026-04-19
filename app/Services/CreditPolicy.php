<?php

namespace App\Services;

use App\Events\WorkspaceLowBalance;
use App\Exceptions\CloneLimitExceededException;
use App\Models\CreditTransaction;
use App\Models\PhaseAgentResult;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditPolicy
{
    public function assertHasBalance(Workspace $workspace): void
    {
        if ($workspace->credits_balance_cents <= 0) {
            throw new InsufficientCreditsException('Guthaben aufgebraucht. Bitte den Admin kontaktieren.');
        }
    }

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

            $lockKey = "low_balance_alert:{$workspace->id}";
            if (! Cache::has($lockKey)) {
                Cache::put($lockKey, true, now()->addHours(24));
                WorkspaceLowBalance::dispatch($workspace, $currentBalance, $thresholdPercent);
            }

            return true;
        }

        return false;
    }

    /**
     * Pre-flight check: wirft AgentDailyLimitExceededException wenn das Tageslimit
     * bereits vollständig ausgeschöpft ist — BEVOR der API-Call gemacht wird.
     */
    public function assertDailyLimitNotReached(Workspace $workspace, string $agentKey): void
    {
        $dailyLimitCents = (int) config("services.anthropic.agent_daily_limits.{$agentKey}", 0);

        if ($dailyLimitCents <= 0) {
            return;
        }

        $spentToday = $this->agentSpendingToday($workspace, $agentKey);

        if ($spentToday >= $dailyLimitCents) {
            throw new AgentDailyLimitExceededException(
                "Tageslimit für Agent '{$agentKey}' bereits erreicht ({$spentToday}/{$dailyLimitCents} Cents)."
            );
        }
    }

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

    public function checkCloneLimit(Workspace $workspace): void
    {
        $tier = $workspace->tier ?? 'free';

        $maxPending = match ($tier) {
            'pro' => 3,
            'enterprise' => PHP_INT_MAX,
            default => 1,
        };

        if ($maxPending === PHP_INT_MAX) {
            return;
        }

        $pendingCount = PhaseAgentResult::whereHas(
            'projekt',
            fn ($q) => $q->where('workspace_id', $workspace->id)
        )->where('status', 'pending')->count();

        if ($pendingCount >= $maxPending) {
            throw new CloneLimitExceededException(
                "Clone-Limit ({$maxPending}) für Tier '{$tier}' erreicht."
            );
        }
    }

    // Carbon::today() serialisiert als lokaler Timestamp ohne Timezone-Info — PostgreSQL
    // interpretiert das als UTC. ->utc() konvertiert explizit in UTC, damit der Vergleich
    // mit den in UTC gespeicherten timestamptz-Werten korrekt funktioniert.
    private function agentSpendingToday(Workspace $workspace, string $agentKey): int
    {
        return (int) CreditTransaction::where('workspace_id', $workspace->id)
            ->where('type', 'usage')
            ->where('agent_config_key', $agentKey)
            ->where('created_at', '>=', Carbon::today()->utc())
            ->sum(DB::raw('ABS(amount_cents)'));
    }
}
