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

// Workspace-LLM-Endpoint-Lookup — MayringCoder (src/llm/endpoint.py) holt hiermit
// die passende Provider-Config pro Request. Auth: MCP_SERVICE_TOKEN.
Route::middleware([VerifyMcpToken::class, 'throttle:60,1'])
    ->get('/mcp-service/llm-endpoint/{workspace_id}', [\App\Http\Controllers\LlmEndpointController::class, 'show'])
    ->name('mcp-service.llm-endpoint');

// User-LLM-Key-Callback — MayringCoder holt hier den entschlüsselten User-API-Key
// wenn im JWT llm_requires_key=true. Outer-Auth via MCP_SERVICE_TOKEN, Body-JWT
// identifiziert den User. Kein JWT im Authorization-Header akzeptiert.
Route::middleware([VerifyMcpToken::class.':service_only', 'throttle:30,1'])
    ->post('/mcp/user-llm-key', [\App\Http\Controllers\UserLlmKeyController::class, 'show'])
    ->name('mcp.user-llm-key');

// Token-Refresh für Gradio-WebUI — MayringCoder's web_ui.refresh_jwt() callt hier.
// Dual-Auth: Sanctum ODER gültiger RS256-JWT als Bearer.
Route::middleware('throttle:10,1')
    ->post('/mayring/refresh-token', [\App\Http\Controllers\MayringDashboardController::class, 'refreshToken'])
    ->name('mayring.refresh-token');
