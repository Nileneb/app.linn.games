<?php

namespace App\Services;

use App\Models\GameRewardClaim;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GameRewardService
{
    public function __construct(private readonly CreditService $credits) {}

    public function checkAndReward(User $user): void
    {
        $workspace = $user->workspaces()->first();
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

            $alreadyClaimed = GameRewardClaim::where('user_id', $user->id)
                ->where('kills_threshold', $kills)
                ->exists();

            if ($alreadyClaimed) {
                continue;
            }

            $this->claimReward($user, $workspace, $threshold);
        }
    }

    private function claimReward(User $user, Workspace $workspace, array $threshold): void
    {
        DB::transaction(function () use ($user, $workspace, $threshold): void {
            GameRewardClaim::create([
                'id' => Str::uuid(),
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
                $newFactor = max(0.0, ($workspace->discount_factor ?? 1.0) - (float) $threshold['value']);
                $workspace->update(['discount_factor' => $newFactor]);
            }

            Log::info('Game reward claimed', [
                'user_id' => $user->id,
                'kills' => $threshold['kills'],
                'type' => $threshold['type'],
                'value' => $threshold['value'],
                'workspace' => $workspace->id,
            ]);
        });
    }
}
