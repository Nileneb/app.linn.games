<?php

namespace App\Http\Controllers;

use App\Models\GameAction;
use App\Models\GameSession;
use App\Services\GameRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameSessionController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $session = GameSession::create([
            'code' => GameSession::generateCode(),
            'host_user_id' => auth()->id(),
            'status' => 'waiting',
        ]);

        DB::table('game_session_players')->insert([
            'session_id' => $session->id,
            'user_id' => auth()->id(),
            'joined_at' => now(),
        ]);

        return response()->json(['code' => $session->code]);
    }

    public function join(Request $request, string $code): JsonResponse
    {
        $session = GameSession::where('code', $code)
            ->where('status', '!=', 'ended')
            ->firstOrFail();

        $count = DB::table('game_session_players')
            ->where('session_id', $session->id)->count();

        abort_if($count >= 10, 422, 'Session voll');

        DB::table('game_session_players')->upsert(
            ['session_id' => $session->id, 'user_id' => auth()->id(), 'joined_at' => now()],
            ['session_id', 'user_id'],
        );

        return response()->json([
            'code' => $session->code,
            'is_host' => $session->isHost(auth()->id()),
        ]);
    }

    public function show(string $code): JsonResponse
    {
        $session = GameSession::where('code', $code)->firstOrFail();

        $isMember = DB::table('game_session_players')
            ->where('session_id', $session->id)
            ->where('user_id', auth()->id())
            ->exists();
        abort_unless($isMember, 403);

        $players = DB::table('game_session_players')
            ->where('session_id', $session->id)
            ->join('users', 'users.id', '=', 'game_session_players.user_id')
            ->select('users.id', 'users.name', 'game_session_players.score', 'game_session_players.kills')
            ->get();

        return response()->json(['session' => $session, 'players' => $players]);
    }

    public function saveScore(Request $request, string $code): JsonResponse
    {
        $validated = $request->validate([
            'score' => 'required|integer|min:0|max:9999999',
            'kills' => 'required|integer|min:0|max:10000',
            'wave' => 'integer|min:1|max:9999',
        ]);

        $session = GameSession::where('code', $code)
            ->where('status', '!=', 'ended')
            ->firstOrFail();

        $newKills = 0;
        $user = null;

        DB::transaction(function () use ($session, $validated, $request, &$newKills, &$user): void {
            $row = DB::table('game_session_players')
                ->where('session_id', $session->id)
                ->where('user_id', auth()->id())
                ->lockForUpdate()
                ->first(['kills']);

            $previousKills = $row?->kills ?? 0;

            DB::table('game_session_players')
                ->where('session_id', $session->id)
                ->where('user_id', auth()->id())
                ->update([
                    'score' => $validated['score'],
                    'kills' => $validated['kills'],
                ]);

            $newKills = max(0, $validated['kills'] - $previousKills);
            if ($newKills > 0) {
                $user = $request->user();
                $user->increment('total_kills', $newKills);
                $user->refresh();
            }
        });

        if ($newKills > 0 && $user !== null) {
            try {
                app(GameRewardService::class)->checkAndReward($user);
            } catch (\Throwable $e) {
                Log::error('GameRewardService::checkAndReward failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function logAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|max:50',
            'session_id' => 'nullable|uuid',
            'projekt_id' => 'nullable|uuid',
            'enemy_type' => 'nullable|string|max:50',
            'cluster_id' => 'nullable|string|max:100',
            'paper_id' => 'nullable|uuid',
            'reaction_ms' => 'nullable|integer|min:0',
            'wave' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
        ]);

        GameAction::create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function end(Request $request, string $code): JsonResponse
    {
        $session = GameSession::where('code', $code)->firstOrFail();
        abort_unless($session->isHost(auth()->id()), 403, 'Nur der Host kann die Session beenden');

        $session->update(['status' => 'ended']);

        return response()->json(['ok' => true]);
    }
}
