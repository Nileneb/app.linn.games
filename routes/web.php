<?php

use App\Http\Controllers\ContactController;
use App\Models\PageView;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    PageView::firstOrCreate([], ['visits' => 0])->increment('visits');

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
});
