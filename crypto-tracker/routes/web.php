<?php

use App\Http\Controllers\ChartController;
use App\Http\Controllers\DexScreenerController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\OpenAIController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\WhaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WhaleController::class, 'index'])->name('whales');
Route::get('/whales/{wallet}/transactions', [WhaleController::class, 'transactions'])->name('whales.transactions');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/telegram-link', [ProfileController::class, 'telegramLink'])->name('profile.telegram-link');
    Route::post('/profile/telegram-unlink', [ProfileController::class, 'telegramUnlink'])->name('profile.telegram-unlink');
});

Route::get('/dashboard', [DexScreenerController::class, 'index'])->name('dashboard');
Route::get('/latest-token-profiles/{group?}', [DexScreenerController::class, 'getLatestTokenProfiles'])
    ->where('group', '[01]')->name('dex.latestTokenProfiles');
Route::get('/latest-boosted-tokens/{group?}', [DexScreenerController::class, 'getLatestBoostedTokens'])
    ->where('group', '[01]')->name('dex.getLatestBoostedTokens');
Route::get('/most-boosted-tokens/{group?}', [DexScreenerController::class, 'getMostBoostedTokens'])
    ->where('group', '[01]')->name('dex.getMostBoostedTokens');

Route::get('/chart', [ChartController::class, 'show'])->name('chart');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/chart/check-source', [ChartController::class, 'checkSource'])->name('chart.checkSource');

    Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::post('/integrations', [IntegrationController::class, 'store'])->name('integrations.store');
    Route::patch('/integrations/{integration}', [IntegrationController::class, 'update'])->name('integrations.update');
    Route::delete('/integrations/{integration}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
    Route::post('/integrations/check', [IntegrationController::class, 'check'])->name('integrations.check');

    Route::post('/openai/respond', [OpenAIController::class, 'respond']);

    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::patch('/watchlist/{wallet}', [WatchlistController::class, 'update'])->name('watchlist.update');
    Route::delete('/watchlist/{wallet}', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
});

require __DIR__.'/auth.php';
