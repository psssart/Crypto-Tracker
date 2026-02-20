<?php

namespace App\Services\WalletHistory;

use App\Contracts\WalletHistoryProvider;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\ApiService;
use App\Services\CoinGeckoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MoralisHistoryProvider implements WalletHistoryProvider
{
    private const BASE_URL = 'https://deep-index.moralis.io/api/v2.2';

    private const CHAIN_MAP = [
        'ethereum' => '0x1',
        'polygon' => '0x89',
        'bsc' => '0x38',
        'arbitrum' => '0xa4b1',
        'base' => '0x2105',
    ];

    private const COIN_MAP = [
        'ethereum' => 'ethereum',
        'polygon' => 'matic-network',
        'bsc' => 'binancecoin',
        'arbitrum' => 'ethereum',
        'base' => 'ethereum',
    ];

    public function __construct(
        private ApiService $api,
        private CoinGeckoService $coinGecko,
        private string $apiKey,
    ) {}

    public static function supportedNetworks(): array
    {
        return array_keys(self::CHAIN_MAP);
    }

    public function syncTransactions(Wallet $wallet): int
    {
        $chain = self::CHAIN_MAP[$wallet->network->slug];
        $headers = ['X-API-Key' => $this->apiKey];

        $response = $this->api->get(
            self::BASE_URL . '/' . $wallet->address,
            ['chain' => $chain, 'order' => 'desc', 'limit' => 100],
            $headers,
        );

        $results = $response->json('result') ?? [];

        foreach ($results as $tx) {
            Transaction::updateOrCreate(
                ['hash' => $tx['hash']],
                [
                    'wallet_id' => $wallet->id,
                    'from_address' => strtolower($tx['from_address'] ?? ''),
                    'to_address' => strtolower($tx['to_address'] ?? ''),
                    'amount' => self::weiToEther($tx['value'] ?? '0'),
                    'fee' => self::calculateFee($tx),
                    'block_number' => $tx['block_number'] ?? null,
                    'mined_at' => isset($tx['block_timestamp'])
                        ? Carbon::parse($tx['block_timestamp'])
                        : null,
                ],
            );
        }

        Log::info('MoralisHistoryProvider: stored transactions', [
            'wallet_id' => $wallet->id,
            'count' => count($results),
        ]);

        return count($results);
    }

    public function syncBalance(Wallet $wallet): void
    {
        $chain = self::CHAIN_MAP[$wallet->network->slug];
        $headers = ['X-API-Key' => $this->apiKey];

        $response = $this->api->get(
            self::BASE_URL . '/' . $wallet->address . '/balance',
            ['chain' => $chain],
            $headers,
        );

        $balanceNative = self::weiToEther($response->json('balance') ?? '0');
        $coinId = self::COIN_MAP[$wallet->network->slug] ?? null;

        $balanceUsd = '0';
        if ($coinId) {
            try {
                $priceUsd = $this->coinGecko->getPriceUsd($coinId);
                $balanceUsd = bcmul((string) $balanceNative, (string) $priceUsd, 18);
            } catch (\Throwable $e) {
                Log::warning('MoralisHistoryProvider: CoinGecko price fetch failed', [
                    'coin' => $coinId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $wallet->update(['balance_usd' => $balanceUsd]);
    }

    private static function weiToEther(string $wei): string
    {
        return bcdiv($wei, '1000000000000000000', 18);
    }

    private static function calculateFee(array $tx): ?string
    {
        if (isset($tx['gas_price'], $tx['receipt_gas_used'])) {
            $feeWei = bcmul((string) $tx['gas_price'], (string) $tx['receipt_gas_used']);
            return self::weiToEther($feeWei);
        }

        return null;
    }
}
