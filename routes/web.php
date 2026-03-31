<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\DsgvoController;
use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::view('Impressum.html', 'legal.impressum')->name('impressum');
Route::view('dsgvo.html', 'legal.dsgvo')->name('dsgvo');
Route::view('AGB.html', 'legal.agb')->name('agb');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');
    Volt::route('settings/webhooks', 'settings.webhooks')->name('webhooks.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    Route::get('dsgvo/export', [DsgvoController::class, 'export'])->name('dsgvo.export');
    Route::delete('dsgvo/delete-account', [DsgvoController::class, 'deleteAccount'])->name('dsgvo.delete-account');

    Route::get('recherche', fn () => view('recherche.index'))->name('recherche');
    Route::get('recherche/{projekt}', fn (Projekt $projekt) => view('recherche.show', ['projekt' => $projekt]))->name('recherche.projekt');
});
