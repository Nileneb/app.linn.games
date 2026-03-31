<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DsgvoController extends Controller
{
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'user' => $user->only(['id', 'name', 'email', 'created_at', 'updated_at']),
            'contacts' => $user->contacts ?? [],
            'consents' => $user->consents ?? [],
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="dsgvo-export.json"',
        ]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $user->delete();

        return response()->json(['message' => 'Konto und alle verknüpften Daten wurden gelöscht.']);
    }
}
