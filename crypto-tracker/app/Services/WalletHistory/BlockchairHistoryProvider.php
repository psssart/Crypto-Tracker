<?php

namespace App\Services\WalletHistory;

use App\Contracts\WalletHistoryProvider;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\ApiService;
use App\Services\CoinGeckoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class BlockchairHistoryProvider implements WalletHistoryProvider
{
    private const BASE_URL = 'https://api.blockchair.com/bitcoin';
    private const SATOSHI_DIVISOR = '100000000';
    private const COINGECKO_ID = 'bitcoin';

    public function __construct(
        private ApiService $api,
        private CoinGeckoService $coinGecko,
        private ?string $apiKey = null,
    ) {}

    public static function supportedNetworks(): array
    {
        return ['bitcoin'];
    }

    public function syncTransactions(Wallet $wallet): int
    {
        $url = self::BASE_URL . '/dashboards/address/' . $wallet->address;

        $query = ['transaction_details' => 'true', 'limit' => '100,0'];
        if ($this->apiKey) {
            $query['key'] = $this->apiKey;
        }

        $response = $this->api->get($url, $query);

        $addressData = $response->json("data.{$wallet->address}") ?? [];
        $transactions = $addressData['transactions'] ?? [];

        $count = 0;
        foreach ($transactions as $tx) {
            if ($this->upsertTransaction($wallet, $tx)) {
                $count++;
            }
        }

        Log::info('BlockchairHistoryProvider: stored transactions', [
            'wallet_id' => $wallet->id,
            'count' => $count,
        ]);

        return $count;
    }

    public function fetchTransactions(Wallet $wallet, Carbon $from, Carbon $to): int
    {
        $url = self::BASE_URL . '/dashboards/address/' . $wallet->address;

        $query = [
            'transaction_details' => 'true',
            'limit' => '100,0',
            'q' => 'time(' . $from->startOfDay()->format('Y-m-d H:i:s') . '..' . $to->endOfDay()->format('Y-m-d H:i:s') . ')',
        ];
        if ($this->apiKey) {
            $query['key'] = $this->apiKey;
        }

        // Blockchair paginates via offset in limit param
        $offset = 0;
        $total = 0;
        $maxPages = 10;

        for ($page = 0; $page < $maxPages; $page++) {
            $query['limit'] = "100,$offset";

            $response = $this->api->get($url, $query);

            $addressData = $response->json("data.{$wallet->address}") ?? [];
            $transactions = $addressData['transactions'] ?? [];

            if (empty($transactions)) {
                break;
            }

            foreach ($transactions as $tx) {
                if ($this->upsertTransaction($wallet, $tx)) {
                    $total++;
                }
            }

            if (count($transactions) < 100) {
                break;
            }
            $offset += 100;
        }

        Log::info('BlockchairHistoryProvider: fetched transactions for date range', [
            'wallet_id' => $wallet->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'count' => $total,
        ]);

        return $total;
    }

    public function syncBalance(Wallet $wallet): void
    {
        $url = self::BASE_URL . '/dashboards/address/' . $wallet->address;

        $query = [];
        if ($this->apiKey) {
            $query['key'] = $this->apiKey;
        }

        $response = $this->api->get($url, $query);

        $addressData = $response->json("data.{$wallet->address}.address") ?? [];
        $balanceSat = (string) ($addressData['balance'] ?? '0');
        $balanceNative = self::satoshiToBtc($balanceSat);

        $balanceUsd = '0';
        try {
            $priceUsd = $this->coinGecko->getPriceUsd(self::COINGECKO_ID);
            $balanceUsd = bcmul((string) $balanceNative, (string) $priceUsd, 18);
        } catch (\Throwable $e) {
            Log::warning('BlockchairHistoryProvider: CoinGecko price fetch failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $wallet->update(['balance_usd' => $balanceUsd]);
    }

    private function upsertTransaction(Wallet $wallet, array $tx): bool
    {
        $hash = $tx['hash'] ?? null;
        if (! $hash) {
            return false;
        }

        $balanceChangeSat = (string) ($tx['balance_change'] ?? '0');
        $feeSat = (string) ($tx['fee'] ?? '0');
        $absChangeSat = ltrim($balanceChangeSat, '-');
        $isIncoming = bccomp($balanceChangeSat, '0', 0) >= 0;

        $actualAmountSat = $isIncoming
            ? $absChangeSat
            : bcsub($absChangeSat, $feeSat, 0);

        Transaction::updateOrCreate(
            ['hash' => $hash, 'wallet_id' => $wallet->id],
            [
                'from_address' => $isIncoming ? 'External / Multiple' : $wallet->address,
                'to_address' => $isIncoming ? $wallet->address : 'External / Multiple',
                'amount' => self::satoshiToBtc($actualAmountSat),
                'fee' => self::satoshiToBtc($feeSat),
                'block_number' => $tx['block_id'] ?? null,
                'mined_at' => isset($tx['time']) ? Carbon::parse($tx['time']) : null,
            ],
        );

        return true;
    }

    private static function satoshiToBtc(string $satoshi): string
    {
        return bcdiv($satoshi, self::SATOSHI_DIVISOR, 18);
    }
}
