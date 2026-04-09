<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GitHubAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        // 1. Find by provider_id (returning user)
        $user = User::where('provider', 'github')
            ->where('provider_id', $githubUser->getId())
            ->first();

        // 2. Find by email → link account (only if GitHub email is verified)
        $githubEmail = $githubUser->getEmail();
        $emailVerified = (bool) ($githubUser->getRaw()['email_verified'] ?? false);

        if (! $user && $githubEmail && $emailVerified) {
            $user = User::where('email', $githubEmail)->first();
            if ($user) {
                $user->update([
                    'provider' => 'github',
                    'provider_id' => $githubUser->getId(),
                ]);
            }
        }

        // 3. Create new waitlisted user
        if (! $user) {
            $user = User::create([
                'name' => $githubUser->getName() ?? $githubUser->getNickname() ?? 'GitHub User',
                'email' => $githubEmail,
                'provider' => 'github',
                'provider_id' => $githubUser->getId(),
                'status' => 'waitlisted',
                'password' => Str::random(40),
            ]);
        }

        // 4. Not active → pending approval
        if (! $user->isActive()) {
            return redirect()->route('pending-approval');
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
