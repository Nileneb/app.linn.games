<?php

use App\Http\Controllers\LangdockWebhookController;
use App\Http\Controllers\PaperRagController;
use App\Http\Middleware\VerifyLangdockSignature;
use App\Http\Middleware\VerifyMcpToken;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function () {
        return request()->user();
    });
});

Route::post('/webhooks/langdock', [LangdockWebhookController::class, 'handle'])
    ->middleware(VerifyLangdockSignature::class);

Route::middleware(VerifyMcpToken::class)->group(function () {
    Route::post('/papers/ingest', [PaperRagController::class, 'ingest']);
    Route::get('/papers/rag-search', [PaperRagController::class, 'search']);
});
