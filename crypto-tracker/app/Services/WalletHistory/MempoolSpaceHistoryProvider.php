<?php

namespace App\Services\WalletHistory;

use App\Contracts\WalletHistoryProvider;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\ApiService;
use App\Services\CoinGeckoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MempoolSpaceHistoryProvider implements WalletHistoryProvider
{
    private const BASE_URL = 'https://mempool.space/api';
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
        $url = self::BASE_URL . '/address/' . $wallet->address . '/txs';

        $response = $this->api->get($url);
        $transactions = $response->json() ?? [];

        $walletAddr = strtolower($wallet->address);
        $count = 0;

        foreach ($transactions as $tx) {
            $txid = $tx['txid'] ?? null;
            if (!$txid) {
                continue;
            }

            // Calculate net value for this wallet from vin/vout
            $sent = '0';
            foreach ($tx['vin'] ?? [] as $input) {
                $inputAddr = strtolower($input['prevout']['scriptpubkey_address'] ?? '');
                if ($inputAddr === $walletAddr) {
                    $sent = bcadd($sent, (string) ($input['prevout']['value'] ?? 0), 0);
                }
            }

            $received = '0';
            foreach ($tx['vout'] ?? [] as $output) {
                $outputAddr = strtolower($output['scriptpubkey_address'] ?? '');
                if ($outputAddr === $walletAddr) {
                    $received = bcadd($received, (string) ($output['value'] ?? 0), 0);
                }
            }

            $netSat = bcsub($received, $sent, 0);
            $isIncoming = bccomp($netSat, '0', 0) >= 0;
            $absAmountSat = ltrim($netSat, '-');

            $feeSat = (string) ($tx['fee'] ?? '0');

            Transaction::updateOrCreate(
                ['hash' => $txid, 'wallet_id' => $wallet->id],
                [
                    'from_address' => $isIncoming ? 'External / Multiple' : $wallet->address,
                    'to_address' => $isIncoming ? $wallet->address : 'External / Multiple',
                    'amount' => self::satoshiToBtc($absAmountSat),
                    'fee' => self::satoshiToBtc($feeSat),
                    'block_number' => $tx['status']['block_height'] ?? null,
                    'mined_at' => isset($tx['status']['block_time'])
                        ? Carbon::createFromTimestamp($tx['status']['block_time'])
                        : null,
                ],
            );
            $count++;
        }

        Log::info('MempoolSpaceHistoryProvider: stored transactions', [
            'wallet_id' => $wallet->id,
            'count' => $count,
        ]);

        return $count;
    }

    public function syncBalance(Wallet $wallet): void
    {
        $url = self::BASE_URL . '/address/' . $wallet->address;

        $response = $this->api->get($url);

        $funded = (string) ($response->json('chain_stats.funded_txo_sum') ?? '0');
        $spent = (string) ($response->json('chain_stats.spent_txo_sum') ?? '0');
        $balanceSat = bcsub($funded, $spent, 0);
        $balanceNative = self::satoshiToBtc($balanceSat);

        $balanceUsd = '0';
        try {
            $priceUsd = $this->coinGecko->getPriceUsd(self::COINGECKO_ID);
            $balanceUsd = bcmul((string) $balanceNative, (string) $priceUsd, 18);
        } catch (\Throwable $e) {
            Log::warning('MempoolSpaceHistoryProvider: CoinGecko price fetch failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $wallet->update(['balance_usd' => $balanceUsd]);
    }

    private static function satoshiToBtc(string $satoshi): string
    {
        return bcdiv($satoshi, self::SATOSHI_DIVISOR, 18);
    }
}
