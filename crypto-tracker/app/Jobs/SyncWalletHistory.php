<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ApiService;
use App\Services\CryptoProviderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SyncWalletHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    private const MORALIS_BASE = 'https://deep-index.moralis.io/api/v2.2';

    private const CHAIN_MAP = [
        'ethereum' => '0x1',
        'polygon' => '0x89',
        'bsc' => '0x38',
        'arbitrum' => '0xa4b1',
        'base' => '0x2105',
    ];

    public function __construct(
        public Wallet $wallet,
        public ?int $userId = null,
    ) {
    }

    public function handle(ApiService $api, CryptoProviderService $providerService): void
    {
        Log::info('SyncWalletHistory dispatched', [
            'wallet_id' => $this->wallet->id,
            'address' => $this->wallet->address,
            'network' => $this->wallet->network->slug ?? null,
            'user_id' => $this->userId,
        ]);

        $user = $this->userId ? User::find($this->userId) : null;
        $apiKey = $providerService->resolveApiKey('moralis', $user);

        if (!$apiKey) {
            Log::warning('SyncWalletHistory: no Moralis API key available', [
                'wallet_id' => $this->wallet->id,
            ]);
            return;
        }

        $networkSlug = $this->wallet->network->slug ?? null;
        $chain = self::CHAIN_MAP[$networkSlug] ?? null;

        if (!$chain) {
            Log::warning('SyncWalletHistory: unsupported network for Moralis', [
                'network' => $networkSlug,
            ]);
            return;
        }

        $headers = ['X-API-Key' => $apiKey];

        $this->syncTransactions($api, $headers, $chain);
        $this->syncBalance($api, $headers, $chain);

        $this->wallet->update(['last_synced_at' => now()]);

        Log::info('SyncWalletHistory completed', [
            'wallet_id' => $this->wallet->id,
        ]);
    }

    private function syncTransactions(ApiService $api, array $headers, string $chain): void
    {
        $url = self::MORALIS_BASE . '/' . $this->wallet->address;

        $response = $api->get($url, [
            'chain' => $chain,
            'order' => 'desc',
            'limit' => 100,
        ], $headers);

        $results = $response->json('result') ?? [];

        foreach ($results as $tx) {
            Transaction::updateOrCreate(
                ['hash' => $tx['hash']],
                [
                    'wallet_id' => $this->wallet->id,
                    'from_address' => strtolower($tx['from_address'] ?? ''),
                    'to_address' => strtolower($tx['to_address'] ?? ''),
                    'amount' => $this->weiToEther($tx['value'] ?? '0'),
                    'fee' => $this->calculateFee($tx),
                    'block_number' => $tx['block_number'] ?? null,
                    'mined_at' => isset($tx['block_timestamp'])
                        ? Carbon::parse($tx['block_timestamp'])
                        : null,
                ],
            );
        }

        Log::info('SyncWalletHistory: stored transactions', [
            'wallet_id' => $this->wallet->id,
            'count' => count($results),
        ]);
    }

    private function syncBalance(ApiService $api, array $headers, string $chain): void
    {
        $url = self::MORALIS_BASE . '/' . $this->wallet->address . '/balance';

        $response = $api->get($url, ['chain' => $chain], $headers);

        $balanceWei = $response->json('balance') ?? '0';
        $balanceNative = $this->weiToEther($balanceWei);

        // Estimate USD value using CoinGecko for the network's native coin
        $coinMap = [
            'ethereum' => 'ethereum',
            'polygon' => 'matic-network',
            'bsc' => 'binancecoin',
            'arbitrum' => 'ethereum',
            'base' => 'ethereum',
        ];

        $networkSlug = $this->wallet->network->slug;
        $coinId = $coinMap[$networkSlug] ?? null;

        $balanceUsd = '0';
        if ($coinId) {
            try {
                $coinGecko = app(\App\Services\CoinGeckoService::class);
                $priceUsd = $coinGecko->getPriceUsd($coinId);
                $balanceUsd = bcmul((string) $balanceNative, (string) $priceUsd, 18);
            } catch (\Throwable $e) {
                Log::warning('SyncWalletHistory: CoinGecko price fetch failed', [
                    'coin' => $coinId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->wallet->update(['balance_usd' => $balanceUsd]);
    }

    private function weiToEther(string $wei): string
    {
        return bcdiv($wei, '1000000000000000000', 18);
    }

    private function calculateFee(array $tx): ?string
    {
        if (isset($tx['gas_price'], $tx['receipt_gas_used'])) {
            $feeWei = bcmul((string) $tx['gas_price'], (string) $tx['receipt_gas_used']);
            return $this->weiToEther($feeWei);
        }

        return null;
    }
}
