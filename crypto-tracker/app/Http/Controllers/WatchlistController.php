<?php

namespace App\Http\Controllers;

use App\Jobs\SyncWalletHistory;
use App\Models\Network;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WatchlistController extends Controller
{
    public function index(Request $request)
    {
        $wallets = $request->user()
            ->wallets()
            ->with('network')
            ->get();

        $networks = Network::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'currency_symbol', 'explorer_url']);

        return Inertia::render('Watchlist', [
            'wallets' => $wallets,
            'networks' => $networks,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'network_id' => 'required|exists:networks,id',
            'address' => 'required|string|max:255',
            'custom_label' => 'nullable|string|max:255',
        ]);

        $wallet = Wallet::firstOrCreate(
            [
                'network_id' => $validated['network_id'],
                'address' => strtolower($validated['address']),
            ],
        );

        $user = $request->user();

        if ($user->wallets()->where('wallet_id', $wallet->id)->exists()) {
            return back()->withErrors(['address' => 'This wallet is already in your watchlist.']);
        }

        $user->wallets()->attach($wallet->id, [
            'custom_label' => $validated['custom_label'] ?? null,
        ]);

        SyncWalletHistory::dispatch($wallet, $user->id);

        return back();
    }

    public function update(Request $request, Wallet $wallet)
    {
        $user = $request->user();

        if (!$user->wallets()->where('wallet_id', $wallet->id)->exists()) {
            abort(403);
        }

        $validated = $request->validate([
            'custom_label' => 'nullable|string|max:255',
            'is_notified' => 'sometimes|boolean',
            'notify_threshold_usd' => 'nullable|numeric|min:0',
            'notify_via' => 'sometimes|in:email,telegram,both',
            'notify_direction' => 'sometimes|in:all,incoming,outgoing',
            'notify_cooldown_minutes' => 'nullable|integer|min:0|max:10080',
            'notes' => 'nullable|string|max:5000',
        ]);

        $user->wallets()->updateExistingPivot($wallet->id, $validated);

        return back();
    }

    public function destroy(Request $request, Wallet $wallet)
    {
        $user = $request->user();

        if (!$user->wallets()->where('wallet_id', $wallet->id)->exists()) {
            abort(403);
        }

        $user->wallets()->detach($wallet->id);

        return back();
    }
}
