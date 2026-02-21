<?php

namespace App\Services\WalletHistory;

use App\Contracts\WalletHistoryProvider;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\ApiService;
use App\Services\CoinGeckoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TronGridHistoryProvider implements WalletHistoryProvider
{
    private const BASE_URL = 'https://api.trongrid.io/v1/accounts';
    private const SUN_DIVISOR = '1000000';
    private const COINGECKO_ID = 'tron';

    public function __construct(
        private ApiService $api,
        private CoinGeckoService $coinGecko,
        private ?string $apiKey = null,
    ) {}

    public static function supportedNetworks(): array
    {
        return ['tron'];
    }

    public function syncTransactions(Wallet $wallet): int
    {
        $url = self::BASE_URL . '/' . $wallet->address . '/transactions';

        $headers = [];
        if ($this->apiKey) {
            $headers['TRON-PRO-API-KEY'] = $this->apiKey;
        }

        $response = $this->api->get($url, [
            'limit' => 50,
            'only_confirmed' => 'true',
            'order_by' => 'block_timestamp,desc',
        ], $headers);

        $transactions = $response->json('data') ?? [];

        $count = 0;
        foreach ($transactions as $tx) {
            $txId = $tx['txID'] ?? null;
            if (!$txId) {
                continue;
            }

            // Parse TransferContract from raw_data.contract
            $contract = $tx['raw_data']['contract'][0] ?? [];
            $contractType = $contract['type'] ?? '';
            $paramValue = $contract['parameter']['value'] ?? [];

            $fromAddress = '';
            $toAddress = '';
            $amount = '0';

            if ($contractType === 'TransferContract') {
                $fromAddress = strtolower($paramValue['owner_address'] ?? '');
                $toAddress = strtolower($paramValue['to_address'] ?? '');
                $amount = self::sunToTrx((string) ($paramValue['amount'] ?? '0'));
            }

            Transaction::updateOrCreate(
                ['hash' => $txId],
                [
                    'wallet_id' => $wallet->id,
                    'from_address' => $fromAddress,
                    'to_address' => $toAddress,
                    'amount' => $amount,
                    'fee' => isset($tx['ret'][0]['fee'])
                        ? self::sunToTrx((string) $tx['ret'][0]['fee'])
                        : null,
                    'block_number' => $tx['blockNumber'] ?? null,
                    'mined_at' => isset($tx['block_timestamp'])
                        ? Carbon::createFromTimestampMs($tx['block_timestamp'])
                        : null,
                ],
            );
            $count++;
        }

        Log::info('TronGridHistoryProvider: stored transactions', [
            'wallet_id' => $wallet->id,
            'count' => $count,
        ]);

        return $count;
    }

    public function syncBalance(Wallet $wallet): void
    {
        $url = self::BASE_URL . '/' . $wallet->address;

        $headers = [];
        if ($this->apiKey) {
            $headers['TRON-PRO-API-KEY'] = $this->apiKey;
        }

        $response = $this->api->get($url, [], $headers);

        $balanceSun = (string) ($response->json('data.0.balance') ?? '0');
        $balanceNative = self::sunToTrx($balanceSun);

        $balanceUsd = '0';
        try {
            $priceUsd = $this->coinGecko->getPriceUsd(self::COINGECKO_ID);
            $balanceUsd = bcmul((string) $balanceNative, (string) $priceUsd, 18);
        } catch (\Throwable $e) {
            Log::warning('TronGridHistoryProvider: CoinGecko price fetch failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $wallet->update(['balance_usd' => $balanceUsd]);
    }

    private static function sunToTrx(string $sun): string
    {
        return bcdiv($sun, self::SUN_DIVISOR, 18);
    }
}
