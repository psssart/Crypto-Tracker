<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletHistory\WalletHistoryProviderRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FetchWalletTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public Wallet $wallet,
        public Carbon $from,
        public Carbon $to,
        public ?int $userId = null,
    ) {}

    public function handle(WalletHistoryProviderRegistry $registry): void
    {
        $networkSlug = $this->wallet->network->slug ?? null;

        Log::info('FetchWalletTransactions dispatched', [
            'wallet_id' => $this->wallet->id,
            'address' => $this->wallet->address,
            'network' => $networkSlug,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'user_id' => $this->userId,
        ]);

        $user = $this->userId ? User::find($this->userId) : null;
        $providers = $registry->resolveAll($networkSlug, $user);

        if (empty($providers)) {
            Log::warning('FetchWalletTransactions: no provider available', [
                'wallet_id' => $this->wallet->id,
                'network' => $networkSlug,
            ]);

            return;
        }

        $lastException = null;

        foreach ($providers as $provider) {
            try {
                $count = $provider->fetchTransactions($this->wallet, $this->from, $this->to);

                $this->wallet->update(['last_synced_at' => now()]);

                Log::info('FetchWalletTransactions completed', [
                    'wallet_id' => $this->wallet->id,
                    'provider' => $provider::class,
                    'count' => $count,
                ]);

                return;
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::warning('FetchWalletTransactions: provider failed, trying next', [
                    'wallet_id' => $this->wallet->id,
                    'provider' => $provider::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::error('FetchWalletTransactions: all providers failed', [
            'wallet_id' => $this->wallet->id,
            'network' => $networkSlug,
            'last_error' => $lastException?->getMessage(),
        ]);
    }
}
