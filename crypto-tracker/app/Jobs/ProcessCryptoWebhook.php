<?php

namespace App\Jobs;

use App\Models\Network;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WebhookLog;
use App\Notifications\WalletThresholdAlert;
use App\Services\CoinGeckoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessCryptoWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    private const COIN_MAP = [
        'ethereum' => 'ethereum',
        'polygon' => 'matic-network',
        'bsc' => 'binancecoin',
        'solana' => 'solana',
        'bitcoin' => 'bitcoin',
    ];

    public function __construct(
        public WebhookLog $webhookLog,
    ) {
    }

    public function handle(CoinGeckoService $coinGecko): void
    {
        Log::info('ProcessCryptoWebhook dispatched', [
            'webhook_log_id' => $this->webhookLog->id,
        ]);

        $payload = $this->webhookLog->payload;
        $event = $payload['event'] ?? 'unknown';
        $chainSlug = $payload['chain'] ?? null;
        $data = $payload['data'] ?? [];

        Log::info('ProcessCryptoWebhook: parsed event', [
            'event' => $event,
            'chain' => $chainSlug,
        ]);

        if (!$chainSlug || empty($data)) {
            Log::warning('ProcessCryptoWebhook: missing chain or data in payload', [
                'webhook_log_id' => $this->webhookLog->id,
            ]);
            $this->webhookLog->update(['processed_at' => now()]);
            return;
        }

        $network = Network::where('slug', $chainSlug)->first();
        if (!$network) {
            Log::warning('ProcessCryptoWebhook: unknown network', ['chain' => $chainSlug]);
            $this->webhookLog->update(['processed_at' => now()]);
            return;
        }

        $txHash = $data['tx_hash'] ?? $data['hash'] ?? null;
        $fromAddress = strtolower($data['from_address'] ?? $data['from'] ?? '');
        $toAddress = strtolower($data['to_address'] ?? $data['to'] ?? '');
        $value = $data['value'] ?? '0';
        $blockNumber = $data['block_number'] ?? null;
        $timestamp = $data['block_timestamp'] ?? $data['timestamp'] ?? null;

        // Match wallets tracked in our system by from/to address
        $matchedWallets = Wallet::where('network_id', $network->id)
            ->whereIn('address', array_filter([$fromAddress, $toAddress]))
            ->get();

        if ($matchedWallets->isEmpty()) {
            Log::info('ProcessCryptoWebhook: no tracked wallets matched', [
                'from' => $fromAddress,
                'to' => $toAddress,
            ]);
            $this->webhookLog->update(['processed_at' => now()]);
            return;
        }

        // Store the transaction for each matched wallet
        foreach ($matchedWallets as $wallet) {
            $transaction = Transaction::updateOrCreate(
                ['hash' => $txHash],
                [
                    'wallet_id' => $wallet->id,
                    'from_address' => $fromAddress,
                    'to_address' => $toAddress,
                    'amount' => $this->weiToEther($value),
                    'block_number' => $blockNumber,
                    'mined_at' => $timestamp ? Carbon::parse($timestamp) : null,
                ],
            );

            // Notify users whose thresholds are exceeded
            $this->notifyUsers($wallet, $transaction, $coinGecko);
        }

        $this->webhookLog->update(['processed_at' => now()]);
    }

    private function notifyUsers(Wallet $wallet, Transaction $transaction, CoinGeckoService $coinGecko): void
    {
        $networkSlug = $wallet->network->slug ?? null;
        $coinId = self::COIN_MAP[$networkSlug] ?? null;
        $priceUsd = 0.0;

        if ($coinId) {
            try {
                $priceUsd = $coinGecko->getPriceUsd($coinId);
            } catch (\Throwable $e) {
                Log::warning('ProcessCryptoWebhook: price fetch failed', [
                    'coin' => $coinId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $amountNative = (float) $transaction->amount;
        $amountUsd = $amountNative * $priceUsd;

        $usersToNotify = $wallet->users()
            ->wherePivot('is_notified', true)
            ->wherePivotNotNull('notify_threshold_usd')
            ->get();

        foreach ($usersToNotify as $user) {
            $threshold = (float) $user->pivot->notify_threshold_usd;

            if ($amountUsd >= $threshold) {
                $user->notify(new WalletThresholdAlert(
                    $wallet,
                    $transaction,
                    number_format($amountUsd, 2, '.', ''),
                ));

                Log::info('ProcessCryptoWebhook: threshold alert sent', [
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'amount_usd' => $amountUsd,
                    'threshold' => $threshold,
                ]);
            }
        }
    }

    private function weiToEther(string $wei): string
    {
        return bcdiv($wei, '1000000000000000000', 18);
    }
}
