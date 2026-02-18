<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
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

        return Inertia::render('Transactions', [
            'wallets' => $wallets->values(),
            'activeWalletId' => $activeWalletId,
            'transactions' => $transactions,
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
        ]);
    }
}
