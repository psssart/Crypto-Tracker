<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCryptoWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public WebhookLog $webhookLog,
    ) {
    }

    public function handle(): void
    {
        Log::info('ProcessCryptoWebhook dispatched', [
            'webhook_log_id' => $this->webhookLog->id,
        ]);

        // TODO: Parse webhook payload to determine event type
        // TODO: Match transactions to tracked wallets
        // TODO: Notify users if thresholds are exceeded

        $this->webhookLog->update(['processed_at' => now()]);
    }
}
