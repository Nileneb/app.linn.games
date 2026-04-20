<?php

namespace App\Http\Controllers;

use App\Services\JwtIssuer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class MayringDashboardController extends Controller
{
    public function __construct(private readonly JwtIssuer $jwtIssuer) {}

    public function redirect(Request $request)
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace();

        $jwt = $this->jwtIssuer->issueForUser($user, $workspace);

        $code = Str::uuid()->toString();
        Redis::setex("mayring_ui_code:{$code}", 60, $jwt);

        $url = rtrim(config('services.mayring.ui_url', 'https://mcp.linn.games/ui'), '/')
            . '/?code=' . $code;

        return redirect()->away($url);
    }

    public function exchangeCode(Request $request)
    {
        $code = (string) $request->query('code', '');

        if (!$code || !Str::isUuid($code)) {
            return response()->json(['error' => 'invalid code'], 400);
        }

        $key   = "mayring_ui_code:{$code}";
        $token = Redis::get($key);

        if (!$token) {
            return response()->json(['error' => 'code expired or already used'], 401);
        }

        Redis::del($key);

        return response()->json(['token' => $token]);
    }
}
