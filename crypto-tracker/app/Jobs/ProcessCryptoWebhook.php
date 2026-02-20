<?php

namespace App\Jobs;

use App\Contracts\CryptoWebhookHandler;
use App\DTOs\ParsedTransaction;
use App\Models\Network;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WebhookLog;
use App\Notifications\WalletThresholdAlert;
use App\Services\CoinGeckoService;
use App\Services\Webhooks\AlchemyWebhookHandler;
use App\Services\Webhooks\MoralisWebhookHandler;
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
        'tron' => 'tron',
        'arbitrum' => 'ethereum',
        'base' => 'ethereum',
    ];

    private const HANDLER_MAP = [
        'moralis' => MoralisWebhookHandler::class,
        'alchemy' => AlchemyWebhookHandler::class,
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

        $source = $this->webhookLog->source;
        $handlerClass = self::HANDLER_MAP[$source] ?? null;

        if (! $handlerClass) {
            Log::warning('ProcessCryptoWebhook: unknown source', [
                'source' => $source,
                'webhook_log_id' => $this->webhookLog->id,
            ]);
            $this->webhookLog->update(['processed_at' => now()]);

            return;
        }

        /** @var CryptoWebhookHandler $handler */
        $handler = app($handlerClass);
        $parsedTransactions = $handler->parseTransactions($this->webhookLog->payload);

        if (empty($parsedTransactions)) {
            Log::info('ProcessCryptoWebhook: no transactions parsed', [
                'webhook_log_id' => $this->webhookLog->id,
                'source' => $source,
            ]);
            $this->webhookLog->update(['processed_at' => now()]);

            return;
        }

        foreach ($parsedTransactions as $parsed) {
            $this->processParsedTransaction($parsed, $coinGecko);
        }

        $this->webhookLog->update(['processed_at' => now()]);
    }

    private function processParsedTransaction(ParsedTransaction $parsed, CoinGeckoService $coinGecko): void
    {
        $network = Network::where('slug', $parsed->networkSlug)->first();
        if (! $network) {
            Log::warning('ProcessCryptoWebhook: unknown network', ['slug' => $parsed->networkSlug]);

            return;
        }

        $matchedWallets = Wallet::where('network_id', $network->id)
            ->whereRaw('LOWER(address) IN (?, ?)', [$parsed->fromAddress, $parsed->toAddress])
            ->get();

        if ($matchedWallets->isEmpty()) {
            Log::info('ProcessCryptoWebhook: no tracked wallets matched', [
                'from' => $parsed->fromAddress,
                'to' => $parsed->toAddress,
                'network' => $parsed->networkSlug,
            ]);

            return;
        }

        foreach ($matchedWallets as $wallet) {
            $transaction = Transaction::updateOrCreate(
                ['hash' => $parsed->txHash],
                [
                    'wallet_id' => $wallet->id,
                    'from_address' => $parsed->fromAddress,
                    'to_address' => $parsed->toAddress,
                    'amount' => $parsed->amount,
                    'block_number' => $parsed->blockNumber,
                    'mined_at' => $parsed->minedAt,
                ],
            );

            $this->notifyUsers($wallet, $transaction, $coinGecko);
        }
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

        $walletAddress = strtolower($wallet->address);
        $isIncoming = strtolower($transaction->to_address) === $walletAddress;
        $isOutgoing = strtolower($transaction->from_address) === $walletAddress;

        $usersToNotify = $wallet->users()
            ->wherePivot('is_notified', true)
            ->wherePivotNotNull('notify_threshold_usd')
            ->get();

        foreach ($usersToNotify as $user) {
            $threshold = (float) $user->pivot->notify_threshold_usd;

            if ($amountUsd < $threshold) {
                continue;
            }

            // Direction filter
            $direction = $user->pivot->notify_direction ?? 'all';
            if ($direction === 'incoming' && ! $isIncoming) {
                continue;
            }
            if ($direction === 'outgoing' && ! $isOutgoing) {
                continue;
            }

            // Cooldown check
            $cooldown = $user->pivot->notify_cooldown_minutes;
            $lastNotified = $user->pivot->last_notified_at;
            if ($cooldown && $lastNotified) {
                $lastNotifiedAt = Carbon::parse($lastNotified);
                if ($lastNotifiedAt->addMinutes($cooldown)->isFuture()) {
                    continue;
                }
            }

            $user->notify(new WalletThresholdAlert(
                $wallet,
                $transaction,
                number_format($amountUsd, 2, '.', ''),
            ));

            // Update last_notified_at on the pivot
            $wallet->users()->updateExistingPivot($user->id, [
                'last_notified_at' => now(),
            ]);

            Log::info('ProcessCryptoWebhook: threshold alert sent', [
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'amount_usd' => $amountUsd,
                'threshold' => $threshold,
            ]);
        }
    }
}
