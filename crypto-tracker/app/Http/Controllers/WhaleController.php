<?php

namespace App\Http\Controllers;

use App\Models\Network;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WhaleController extends Controller
{
    public function index(Request $request)
    {
        $excludedNetworks = ['bsc', 'polygon'];

        $query = Wallet::where('is_whale', true)
            ->with('network')
            ->whereHas('network', fn ($q) => $q->whereNotIn('slug', $excludedNetworks));

        if ($network = $request->query('network')) {
            $query->whereHas('network', fn ($q) => $q->where('slug', $network));
        }

        $whales = $query->orderByDesc('balance_usd')->get();
        $networks = Network::where('is_active', true)
            ->whereNotIn('slug', $excludedNetworks)
            ->whereHas('wallets', fn ($q) => $q->where('is_whale', true))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'currency_symbol']);

        $trackedWhaleIds = $request->user()
            ? $request->user()->wallets()->pluck('wallet_id')->toArray()
            : [];

        return Inertia::render('Whales', [
            'whales' => $whales,
            'networks' => $networks,
            'activeNetwork' => $network,
            'trackedWhaleIds' => $trackedWhaleIds,
        ]);
    }

    public function transactions(Wallet $wallet)
    {
        abort_unless($wallet->is_whale, 404);

        $wallet->load('network');

        $transactions = Transaction::where('wallet_id', $wallet->id)
            ->orderByDesc('mined_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return Inertia::render('WhaleTransactions', [
            'wallet' => $wallet,
            'transactions' => $transactions,
        ]);
    }
}
