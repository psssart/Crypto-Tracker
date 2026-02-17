<?php

namespace App\Http\Controllers;

use App\Models\Network;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WhaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Wallet::where('is_whale', true)->with('network');

        if ($network = $request->query('network')) {
            $query->whereHas('network', fn ($q) => $q->where('slug', $network));
        }

        $whales = $query->orderByDesc('balance_usd')->get();
        $networks = Network::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug', 'currency_symbol']);

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
}
