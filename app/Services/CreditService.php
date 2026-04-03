<?php

namespace App\Services;

use App\Models\CreditTransaction;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function assertHasBalance(Workspace $workspace): void
    {
        if ($workspace->credits_balance_cents <= 0) {
            throw new InsufficientCreditsException('Guthaben aufgebraucht. Bitte den Admin kontaktieren.');
        }
    }

    public function deduct(Workspace $workspace, int $tokensUsed, string $agentKey): void
    {
        $cents = $this->toCents($tokensUsed);

        if ($cents <= 0) {
            return;
        }

        DB::transaction(function () use ($workspace, $cents, $tokensUsed, $agentKey): void {
            // Lock workspace for atomic check + deduct operation
            // FOR UPDATE prevents race conditions in concurrent agent calls
            $lockedWorkspace = DB::table('workspaces')
                ->where('id', $workspace->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedWorkspace || $lockedWorkspace->credits_balance_cents < $cents) {
                throw new InsufficientCreditsException(
                    'Guthaben aufgebraucht. Bitte den Admin kontaktieren.'
                );
            }

            $workspace->decrement('credits_balance_cents', $cents);
            CreditTransaction::create([
                'workspace_id'     => $workspace->id,
                'type'             => 'usage',
                'amount_cents'     => -$cents,
                'tokens_used'      => $tokensUsed,
                'agent_config_key' => $agentKey,
            ]);
        });
    }

    public function topUp(Workspace $workspace, int $cents, string $description = ''): void
    {
        DB::transaction(function () use ($workspace, $cents, $description): void {
            $workspace->increment('credits_balance_cents', $cents);
            CreditTransaction::create([
                'workspace_id' => $workspace->id,
                'type'         => 'topup',
                'amount_cents' => $cents,
                'description'  => $description ?: null,
            ]);
        });
    }

    public function toCents(int $tokens): int
    {
        $pricePerK = (int) config('services.langdock.price_per_1k_tokens_cents', 2);
        return (int) ceil($tokens * $pricePerK / 1000);
    }
}
