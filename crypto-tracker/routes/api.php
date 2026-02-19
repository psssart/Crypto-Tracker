<?php

use App\Http\Controllers\Webhooks\TelegramWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('webhooks')->group(function () {
    Route::post('/telegram/webhook', TelegramWebhookController::class)
        ->name('webhooks.telegram');
});
