<?php

namespace App\Http\Controllers;

use App\Jobs\FetchWalletTransactions;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $wallets = $user->wallets()
            ->with('network')
            ->get()
            ->map(fn ($w) => [
                'id' => $w->id,
                'address' => $w->address,
                'custom_label' => $w->pivot->custom_label,
                'network' => $w->network,
            ]);

        $activeWalletId = $request->integer('wallet')
            ?: $wallets->first()?->get('id');

        $dateTo = $request->date('date_to') ?? now();
        $dateFrom = $request->date('date_from') ?? $dateTo->copy()->subMonth();

        // Clamp to max 6 months
        if ($dateFrom->diffInMonths($dateTo, absolute: true) > 6) {
            $dateFrom = $dateTo->copy()->subMonths(6);
        }

        // Don't allow future dates
        if ($dateTo->isFuture()) {
            $dateTo = now();
        }

        $transactions = collect();

        if ($activeWalletId && $wallets->contains(fn ($w) => $w['id'] === $activeWalletId)) {
            $transactions = Transaction::where('wallet_id', $activeWalletId)
                ->where('mined_at', '>=', $dateFrom->startOfDay())
                ->where('mined_at', '<=', $dateTo->endOfDay())
                ->orderByDesc('mined_at')
                ->orderByDesc('id')
                ->get();
        }

        $activeWallet = $activeWalletId
            ? $user->wallets()->where('wallets.id', $activeWalletId)->first()
            : null;

        return Inertia::render('Transactions', [
            'wallets' => $wallets->values(),
            'activeWalletId' => $activeWalletId,
            'transactions' => $transactions,
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
            'lastSyncedAt' => $activeWallet?->last_synced_at?->toIso8601String(),
        ]);
    }

    public function fetch(Request $request)
    {
        $request->validate([
            'wallet_id' => 'required|integer',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $user = $request->user();
        $wallet = $user->wallets()->where('wallets.id', $request->wallet_id)->first();

        if (! $wallet) {
            return back()->with('error', 'Wallet not found.');
        }

        $dateFrom = Carbon::parse($request->date_from);
        $dateTo = Carbon::parse($request->date_to);

        // Clamp to max 6 months
        if ($dateFrom->diffInMonths($dateTo, absolute: true) > 6) {
            $dateFrom = $dateTo->copy()->subMonths(6);
        }

        if ($dateTo->isFuture()) {
            $dateTo = now();
        }

        // Rate limit: include date range in cache key so changing range allows a new fetch
        $cacheKey = "tx_fetch:{$user->id}:{$wallet->id}:{$dateFrom->format('Ymd')}:{$dateTo->format('Ymd')}";

        if (Cache::has($cacheKey)) {
            return back()->with('error', 'Already fetching this range. Please wait before trying again.');
        }

        Cache::put($cacheKey, true, 60);

        FetchWalletTransactions::dispatch($wallet, $dateFrom, $dateTo, $user->id);

        return back()->with('info', 'Fetching transactions for the selected period. Reload the page in a moment.');
    }
}
