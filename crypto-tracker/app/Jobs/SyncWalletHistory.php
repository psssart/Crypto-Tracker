<?php

namespace App\Jobs;

use App\Models\Wallet;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWalletHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Wallet $wallet,
        public ?int $userId = null,
    ) {
    }

    public function handle(): void
    {
        Log::info('SyncWalletHistory dispatched', [
            'wallet_id' => $this->wallet->id,
            'address' => $this->wallet->address,
            'network' => $this->wallet->network->slug ?? null,
            'user_id' => $this->userId,
        ]);

        // TODO: Use Moralis/Alchemy SDK to fetch wallet transaction history
        // TODO: Store transactions in the transactions table
        // TODO: Update wallet balance_usd and last_synced_at
    }
}
