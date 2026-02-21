<?php

namespace App\Services\WalletHistory;

use App\Contracts\WalletHistoryProvider;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\ApiService;
use App\Services\CoinGeckoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class HeliusHistoryProvider implements WalletHistoryProvider
{
    private const TX_BASE_URL = 'https://api.helius.xyz/v0/addresses';
    private const RPC_BASE_URL = 'https://mainnet.helius-rpc.com';
    private const LAMPORT_DIVISOR = '1000000000';
    private const COINGECKO_ID = 'solana';

    public function __construct(
        private ApiService $api,
        private CoinGeckoService $coinGecko,
        private string $apiKey,
    ) {}

    public static function supportedNetworks(): array
    {
        return ['solana'];
    }

    public function syncTransactions(Wallet $wallet): int
    {
        $url = self::TX_BASE_URL . '/' . $wallet->address . '/transactions';

        $response = $this->api->get($url, [
            'api-key' => $this->apiKey,
            'limit' => 100,
        ]);

        $transactions = $response->json() ?? [];

        $count = 0;
        foreach ($transactions as $tx) {
            $signature = $tx['signature'] ?? null;
            if (!$signature) {
                continue;
            }

            // Parse native SOL transfers from Helius Enhanced Transactions
            $nativeTransfers = $tx['nativeTransfers'] ?? [];
            $amount = '0';
            $fromAddress = '';
            $toAddress = '';

            $walletAddr = strtolower($wallet->address);

            foreach ($nativeTransfers as $transfer) {
                $from = strtolower($transfer['fromUserAccount'] ?? '');
                $to = strtolower($transfer['toUserAccount'] ?? '');

                if ($from === $walletAddr || $to === $walletAddr) {
                    $amount = self::lamportToSol((string) ($transfer['amount'] ?? '0'));
                    $fromAddress = $from;
                    $toAddress = $to;
                    break;
                }
            }

            $fee = isset($tx['fee']) ? self::lamportToSol((string) $tx['fee']) : null;

            Transaction::updateOrCreate(
                ['hash' => $signature],
                [
                    'wallet_id' => $wallet->id,
                    'from_address' => $fromAddress,
                    'to_address' => $toAddress,
                    'amount' => $amount,
                    'fee' => $fee,
                    'block_number' => $tx['slot'] ?? null,
                    'mined_at' => isset($tx['timestamp'])
                        ? Carbon::createFromTimestamp($tx['timestamp'])
                        : null,
                ],
            );
            $count++;
        }

        Log::info('HeliusHistoryProvider: stored transactions', [
            'wallet_id' => $wallet->id,
            'count' => $count,
        ]);

        return $count;
    }

    public function syncBalance(Wallet $wallet): void
    {
        $url = self::RPC_BASE_URL . '?' . http_build_query(['api-key' => $this->apiKey]);

        $response = $this->api->post($url, [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getBalance',
            'params' => [$wallet->address],
        ]);

        $lamports = (string) ($response->json('result.value') ?? '0');
        $balanceNative = self::lamportToSol($lamports);

        $balanceUsd = '0';
        try {
            $priceUsd = $this->coinGecko->getPriceUsd(self::COINGECKO_ID);
            $balanceUsd = bcmul((string) $balanceNative, (string) $priceUsd, 18);
        } catch (\Throwable $e) {
            Log::warning('HeliusHistoryProvider: CoinGecko price fetch failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $wallet->update(['balance_usd' => $balanceUsd]);
    }

    private static function lamportToSol(string $lamports): string
    {
        return bcdiv($lamports, self::LAMPORT_DIVISOR, 18);
    }
}
