<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $players = DB::table('game_session_players')
            ->where('session_id', $session->id)
            ->join('users', 'users.id', '=', 'game_session_players.user_id')
            ->select('users.id', 'users.name', 'game_session_players.score', 'game_session_players.kills')
            ->get();

        return response()->json(['session' => $session, 'players' => $players]);
    }
}
