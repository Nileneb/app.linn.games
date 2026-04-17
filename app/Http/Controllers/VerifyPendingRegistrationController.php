<?php

namespace App\Http\Controllers;

use App\Jobs\ReviewRegistrationJob;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class VerifyPendingRegistrationController extends Controller
{
    public function __invoke(string $token): RedirectResponse
    {
        $pending = PendingRegistration::where('token', $token)
            ->where('status', 'pending_email')
            ->first();

        if (!$pending) {
            return redirect()->route('register')
                ->withErrors(['email' => 'Der Verifikationslink ist ungültig.']);
        }

        if ($pending->isExpired()) {
            $pending->delete();
            return redirect()->route('register')
                ->withErrors(['email' => 'Der Verifikationslink ist abgelaufen. Bitte registriere dich erneut.']);
        }

        // forceFill + save() umgeht den 'hashed'-Cast — Passwort ist bereits gehasht
        $user = new User();
        $user->forceFill([
            'name' => $pending->name,
            'email' => $pending->email,
            'password' => $pending->password,
            'status' => 'waitlisted',
            'forschungsfrage' => $pending->forschungsfrage,
            'forschungsbereich' => $pending->forschungsbereich,
            'erfahrung' => $pending->erfahrung,
            'registration_ip' => $pending->registration_ip,
            'registration_country_code' => $pending->registration_country_code,
            'registration_country_name' => $pending->registration_country_name,
            'registration_city' => $pending->registration_city,
        ])->save();

        ReviewRegistrationJob::dispatch($user->id)->delay(now()->addSeconds(10));

        $pending->delete();

        return redirect()->route('login')
            ->with('status', 'email-verified');
    }
}
