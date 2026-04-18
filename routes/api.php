<?php

use App\Http\Controllers\McpAgentController;
use App\Http\Controllers\PaperRagController;
use App\Http\Controllers\StreamingMcpController;
use App\Http\Middleware\SecureMcpHeaders;
use App\Http\Middleware\VerifyMcpToken;
use Illuminate\Support\Facades\Route;

Route::post('stripe/webhook', [\App\Http\Controllers\StripeWebhookController::class, 'handle'])->name('stripe.webhook');

Route::get('/mayring/token-exchange', [\App\Http\Controllers\MayringDashboardController::class, 'exchangeCode'])
    ->middleware('throttle:10,1')
    ->name('mayring.token-exchange');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function () {
        return request()->user();
    });
});

Route::middleware([VerifyMcpToken::class, SecureMcpHeaders::class, 'throttle:mcp'])->group(function () {
    Route::post('/papers/ingest', [PaperRagController::class, 'ingest']);
    Route::get('/papers/rag-search', [PaperRagController::class, 'search']);
    Route::post('/mcp/agent-call', [McpAgentController::class, 'call'])->name('mcp.agent-call');

    // SSE-Streaming: nur interne/lokale IPs (127.0.0.1, Docker-Netz, RFC-1918)
    Route::post('/mcp/agent-call/stream', [StreamingMcpController::class, 'call'])
        ->middleware('mcp.internal')
        ->name('mcp.agent-stream');
});
