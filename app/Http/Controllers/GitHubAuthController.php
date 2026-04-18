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
        try {
            $githubUser = Socialite::driver('github')->user();
        } catch (\Throwable) {
            return redirect()->route('login')
                ->withErrors(['github' => 'GitHub-Autorisierung fehlgeschlagen. Bitte erneut versuchen.']);
        }

        // 1. Find by provider_id (returning user)
        $user = User::where('provider', 'github')
            ->where('provider_id', $githubUser->getId())
            ->first();

        // 2. Find by email → link account
        // GitHub does not return email_verified in its OAuth /user response.
        // Emails returned via user:email scope are trusted by GitHub itself.
        $githubEmail = $githubUser->getEmail();

        if (! $user && $githubEmail) {
            $user = User::where('email', $githubEmail)->first();
            if ($user) {
                $user->update([
                    'provider'    => 'github',
                    'provider_id' => $githubUser->getId(),
                ]);
            }
        }

        // 3. Create new waitlisted user
        if (! $user) {
            if (! $githubEmail) {
                return redirect()->route('login')
                    ->withErrors(['github' => 'GitHub-Konto hat keine öffentliche E-Mail. Bitte E-Mail in den GitHub-Einstellungen freigeben.']);
            }

            $user = User::create([
                'name'        => $githubUser->getName() ?? $githubUser->getNickname() ?? 'GitHub User',
                'email'       => $githubEmail,
                'provider'    => 'github',
                'provider_id' => $githubUser->getId(),
                'status'      => 'waitlisted',
                'password'    => Str::random(40),
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
