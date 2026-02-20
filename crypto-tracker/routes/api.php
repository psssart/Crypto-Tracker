<?php

use App\Http\Controllers\CryptoWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use SergiX44\Nutgram\Nutgram;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/telegram/webhook', function (Nutgram $bot) {
    $bot->run();

    return response()->json(['status' => 'success']);
})->name('webhooks.telegram');

Route::post('/webhooks/moralis', [CryptoWebhookController::class, 'handleMoralis'])
    ->name('webhooks.moralis');
Route::post('/webhooks/alchemy', [CryptoWebhookController::class, 'handleAlchemy'])
    ->name('webhooks.alchemy');
