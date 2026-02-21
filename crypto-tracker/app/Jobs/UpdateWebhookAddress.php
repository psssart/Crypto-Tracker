<?php

namespace App\Jobs;

use App\Models\Wallet;
use App\Services\WebhookAddressService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateWebhookAddress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public Wallet $wallet,
        public string $action,
    ) {
    }

    public function handle(WebhookAddressService $service): void
    {
        Log::info('UpdateWebhookAddress dispatched', [
            'wallet_id' => $this->wallet->id,
            'address' => $this->wallet->address,
            'network' => $this->wallet->network->slug ?? null,
            'action' => $this->action,
        ]);

        if ($this->action === 'add') {
            $service->addAddress($this->wallet);
        } else {
            $service->removeAddress($this->wallet);
        }
    }
}
