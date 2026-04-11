<?php

namespace App\Services;

use App\Models\GameRewardClaim;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameRewardService
{
    public function __construct(private readonly CreditService $credits) {}

    public function checkAndReward(User $user): void
    {
        $workspace = $user->workspaces()->oldest('workspaces.created_at')->first();
        if (! $workspace) {
            return;
        }

        $totalKills = $user->total_kills;
        $thresholds = config('game.kill_rewards', []);

        foreach ($thresholds as $threshold) {
            $kills = (int) $threshold['kills'];

            if ($totalKills < $kills) {
                continue;
            }

            $this->claimReward($user, $workspace, $threshold);
        }
    }

    private function claimReward(User $user, Workspace $workspace, array $threshold): void
    {
        try {
            DB::transaction(function () use ($user, $workspace, $threshold): void {
                // Re-check inside lock — prevents race condition
                $alreadyClaimed = GameRewardClaim::where('user_id', $user->id)
                    ->where('kills_threshold', $threshold['kills'])
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyClaimed) {
                    return;
                }

                GameRewardClaim::create([
                    'user_id' => $user->id,
                    'kills_threshold' => $threshold['kills'],
                    'reward_type' => $threshold['type'],
                    'reward_value' => $threshold['value'],
                    'claimed_at' => now(),
                ]);

                if ($threshold['type'] === 'topup') {
                    $this->credits->topUp(
                        $workspace,
                        (int) $threshold['value'],
                        "Game-Reward: {$threshold['kills']} Kills erreicht"
                    );
                } elseif ($threshold['type'] === 'discount') {
                    // Lock workspace row before reading+modifying discount_factor
                    $freshWorkspace = Workspace::lockForUpdate()->find($workspace->id);
                    $newFactor = max(0.0, ($freshWorkspace->discount_factor ?? 1.0) - (float) $threshold['value']);
                    $freshWorkspace->update(['discount_factor' => $newFactor]);
                }

                Log::info('Game reward claimed', [
                    'user_id' => $user->id,
                    'kills' => $threshold['kills'],
                    'type' => $threshold['type'],
                    'value' => $threshold['value'],
                    'workspace' => $workspace->id,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            Log::info('Game reward already claimed (concurrent race)', [
                'user_id' => $user->id,
                'kills' => $threshold['kills'],
            ]);
        }
    }
}
