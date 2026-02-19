<?php

namespace App\Http\Controllers;

use App\Jobs\SyncWalletHistory;
use App\Jobs\UpdateWebhookAddress;
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
            ->withCount('transactions')
            ->get();

        $networks = Network::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'currency_symbol', 'explorer_url']);

        return Inertia::render('Watchlist', [
            'wallets' => $wallets,
            'networks' => $networks,
        ]);
    }

    public const FREE_WALLET_LIMIT = 4;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'network_id' => 'required|exists:networks,id',
            'address' => 'required|string|max:255',
            'custom_label' => 'nullable|string|max:255',
            'is_notified' => 'sometimes|boolean',
            'notify_threshold_usd' => 'nullable|numeric|min:0',
            'notify_via' => 'sometimes|in:email,telegram,both',
            'notify_direction' => 'sometimes|in:all,incoming,outgoing',
            'notify_cooldown_minutes' => 'nullable|integer|min:0|max:10080',
            'notes' => 'nullable|string|max:5000',
        ]);

        $user = $request->user();

        $hasWalletApiKeys = $user->integrations()
            ->whereIn('provider', ['moralis', 'alchemy', 'etherscan'])
            ->whereNull('revoked_at')
            ->whereNotNull('api_key')
            ->exists();

        if (!$hasWalletApiKeys && $user->wallets()->count() >= self::FREE_WALLET_LIMIT) {
            return back()->withErrors(['limit' => 'You have reached the free limit of ' . self::FREE_WALLET_LIMIT . ' tracked wallets. Configure your API keys in Integrations to track more.']);
        }

        $wallet = Wallet::firstOrCreate(
            [
                'network_id' => $validated['network_id'],
                'address' => strtolower($validated['address']),
            ],
        );

        if ($user->wallets()->where('wallet_id', $wallet->id)->exists()) {
            return back()->withErrors(['address' => 'This wallet is already in your watchlist.']);
        }

        $pivotData = collect($validated)
            ->only(['custom_label', 'is_notified', 'notify_threshold_usd', 'notify_via', 'notify_direction', 'notify_cooldown_minutes', 'notes'])
            ->toArray();

        $user->wallets()->attach($wallet->id, $pivotData);

        if ($wallet->users()->count() === 1) {
            UpdateWebhookAddress::dispatch($wallet, 'add');
        }

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

        if ($wallet->users()->count() === 0) {
            UpdateWebhookAddress::dispatch($wallet, 'remove');
        }

        return back();
    }
}
