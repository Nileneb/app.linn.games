<?php

use App\Http\Controllers\LangdockWebhookController;
use App\Http\Middleware\VerifyLangdockSignature;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function () {
        return request()->user();
    });
});

Route::post('/webhooks/langdock', [LangdockWebhookController::class, 'handle'])
    ->middleware(VerifyLangdockSignature::class);
