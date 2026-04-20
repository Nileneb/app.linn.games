<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\DsgvoController;
use App\Http\Controllers\ProjektExportController;
use App\Models\Recherche\Projekt;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/pitchdeck', fn () => view('pitch'))->name('pitch');

// OAuth 2.0 discovery — consumed by Langdock, Claude Desktop, and other MCP clients
Route::get('/.well-known/oauth-authorization-server', function () {
    return response()->json([
        'issuer'                           => 'https://app.linn.games',
        'authorization_endpoint'           => route('mcp.oauth.authorize'),
        'token_endpoint'                   => 'https://mcp.linn.games/token',
        'response_types_supported'         => ['code'],
        'grant_types_supported'            => ['authorization_code'],
        'code_challenge_methods_supported' => ['S256'],
        'scopes_supported'                 => ['mcp:memory'],
        'paper_search_authorization_endpoint' => route('paper-search.oauth.authorize'),
        'paper_search_token_endpoint'         => route('paper-search.oauth.token'),
        'paper_search_scopes_supported'       => ['paper-search:read'],
    ]);
})->name('oauth.discovery');

Route::middleware('guest')->group(function () {
    Route::get('/auth/github', [\App\Http\Controllers\GitHubAuthController::class, 'redirect'])->name('auth.github');
    Route::get('/api/auth/callback/github', [\App\Http\Controllers\GitHubAuthController::class, 'callback'])->name('auth.github.callback');
});

Route::view('/pending-approval', 'livewire.auth.pending-approval')->name('pending-approval');

Route::get('/register/verify/{token}', \App\Http\Controllers\VerifyPendingRegistrationController::class)
    ->name('register.verify')
    ->middleware('guest');

Route::get('/accept-invitation/{token}', \App\Livewire\Auth\AcceptInvitation::class)
    ->name('invitation.accept')
    ->middleware('guest');

Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::view('Impressum.html', 'legal.impressum')->name('impressum');
Route::view('dsgvo.html', 'legal.dsgvo')->name('dsgvo');
Route::view('AGB.html', 'legal.agb')->name('agb');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    // SSE-Streaming für Dashboard-Chat (aufgerufen via fetch() in big-research-chat)
    Route::post('/chat/stream', \App\Http\Controllers\ChatStreamController::class)->name('chat.stream');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');
    Volt::route('settings/ai-model', 'settings.ai-model')->name('ai-model.edit');

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
    Route::get('recherche/{projekt}', fn (Projekt $projekt) => view('recherche.show', ['projekt' => $projekt]))
        ->middleware('can:view,projekt')
        ->name('recherche.projekt');

    Route::get('recherche/{projekt}/mayring', fn (Projekt $projekt) => view('recherche.mayring', ['projekt' => $projekt]))
        ->middleware('can:view,projekt')
        ->name('recherche.mayring');

    Volt::route('recherche/{projekt}/ergebnisse/{phase}', 'recherche.ergebnisse-anzeigen')
        ->name('recherche.ergebnisse');

    // Export Routes
    Route::get('recherche/{projekt}/export/md', [ProjektExportController::class, 'exportMarkdown'])
        ->name('recherche.export.markdown');
    Route::get('recherche/{projekt}/export/tex', [ProjektExportController::class, 'exportLaTeX'])
        ->name('recherche.export.latex');
    Route::get('recherche/{projekt}/export/mayring', [ProjektExportController::class, 'exportMayringMarkdown'])
        ->name('recherche.export.mayring');
    Route::get('recherche/{projekt}/mayring-stats', [ProjektExportController::class, 'mayringStats'])
        ->name('recherche.mayring.stats');

    // Cluster Explorer
    Route::get('recherche/{projekt}/galaxy', [\App\Http\Controllers\GalaxyController::class, 'show'])
        ->middleware('can:view,projekt')
        ->name('recherche.galaxy');

    Route::get('recherche/{projekt}/galaxy-data', [\App\Http\Controllers\GalaxyDataController::class, 'show'])
        ->middleware('can:view,projekt')
        ->name('recherche.galaxy-data');

    Route::post('/game/sessions', [\App\Http\Controllers\GameSessionController::class, 'create'])
        ->name('game.sessions.create');
    Route::post('/game/sessions/{code}/join', [\App\Http\Controllers\GameSessionController::class, 'join'])
        ->name('game.sessions.join');
    Route::get('/game/sessions/{code}', [\App\Http\Controllers\GameSessionController::class, 'show'])
        ->name('game.sessions.show');
    Route::patch('/game/sessions/{code}/score', [\App\Http\Controllers\GameSessionController::class, 'saveScore'])
        ->name('game.sessions.score');
    Route::patch('/game/sessions/{code}/end', [\App\Http\Controllers\GameSessionController::class, 'end'])
        ->name('game.sessions.end');
    Route::post('/game/actions', [\App\Http\Controllers\GameSessionController::class, 'logAction'])
        ->name('game.actions.log');

    // Credits
    Route::get('credits', \App\Livewire\Credits\Purchase::class)->name('credits.purchase');
    Route::get('credits/usage', \App\Livewire\Credits\Usage::class)->name('credits.usage');
    Route::get('credits/success', fn () => view('credits.success'))->name('credits.success');
    Route::post('credits/checkout', [\App\Http\Controllers\CreditCheckoutController::class, 'redirect'])->name('credits.checkout');

    // MCP OAuth 2.0 — Claude Web / Langdock connector auth (PKCE flow)
    // auth middleware stores intended URL so unauthenticated users are redirected back after login
    Route::middleware(['auth', 'verified'])
        ->get('mcp/authorize', [\App\Http\Controllers\McpOAuthController::class, 'authorize'])
        ->name('mcp.oauth.authorize');

    // Paper Search MCP OAuth 2.0 — all active users (no subscription gate)
    Route::middleware(['auth', 'verified'])
        ->get('/paper-search/authorize', [\App\Http\Controllers\PaperSearchOAuthController::class, 'authorize'])
        ->name('paper-search.oauth.authorize');

    // MayringCoder Dashboard — erstellt kurzlebigen Token → Auto-Login auf mcp.linn.games/ui/
    Route::middleware('mayring.subscription')
        ->get('mayring/dashboard', [\App\Http\Controllers\MayringDashboardController::class, 'redirect'])
        ->name('mayring.dashboard');

    // MayringCoder Subscription (kein Gate — jeder Auth-User kann abonnieren)
    Route::get('einstellungen/mayring-abo', \App\Livewire\Billing\MayringSubscription::class)->name('mayring.subscribe');
    Route::get('einstellungen/llm-endpoints', \App\Livewire\Settings\LlmEndpoints::class)->name('settings.llm-endpoints');

    // MayringCoder Memory-Dashboard (nur für aktive Abonnenten)
    Route::middleware('mayring.subscription')
        ->get('recherche/mayring-memory', \App\Livewire\Recherche\MayringMemoryDashboard::class)
        ->name('mayring.memory');
});

// Paper Search MCP token exchange (unauthenticated by OAuth design — PKCE verifier proves ownership)
Route::post('/paper-search/token', [\App\Http\Controllers\PaperSearchOAuthController::class, 'token'])
    ->name('paper-search.oauth.token');
