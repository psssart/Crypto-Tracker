<?php

namespace App\Services\Webhooks;

use App\Contracts\CryptoWebhookHandler;
use App\DTOs\ParsedTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use kornrunner\Keccak;

class MoralisWebhookHandler implements CryptoWebhookHandler
{
    private const CHAIN_MAP = [
        '0x1'    => 'ethereum',
        '0x89'   => 'polygon',
        '0x38'   => 'bsc',
        '0xa4b1' => 'arbitrum',
        '0x2105' => 'base',
    ];

    public function verifySignature(Request $request): bool
    {
        $signature = $request->header('x-signature');
        if (! $signature) {
            return false;
        }

        $secret = config('services.moralis.api_key');
        if (! $secret) {
            return false;
        }

        $rawBody = $request->getContent();
        $expected = Keccak::hash($rawBody . $secret, 256);

        return hash_equals($expected, $signature);
    }

    /** @return ParsedTransaction[] */
    public function parseTransactions(array $payload): array
    {
        if (empty($payload['confirmed']) || $payload['confirmed'] !== true) {
            return [];
        }

        $chainId = $payload['chainId'] ?? null;
        $networkSlug = self::CHAIN_MAP[$chainId] ?? null;

        if (! $networkSlug) {
            return [];
        }

        $transactions = [];
        $block = $payload['block'] ?? [];
        $blockNumber = isset($block['number']) ? (int) $block['number'] : null;
        $blockTimestamp = isset($block['timestamp']) ? Carbon::createFromTimestamp($block['timestamp']) : null;

        foreach ($payload['txs'] ?? [] as $tx) {
            $from = strtolower($tx['fromAddress'] ?? '');
            $to = strtolower($tx['toAddress'] ?? '');
            $hash = $tx['hash'] ?? null;
            $value = $tx['value'] ?? '0';

            if (! $hash || (! $from && ! $to)) {
                continue;
            }

            $transactions[] = new ParsedTransaction(
                networkSlug: $networkSlug,
                txHash: $hash,
                fromAddress: $from,
                toAddress: $to,
                amount: $this->weiToEther($value),
                blockNumber: $blockNumber,
                minedAt: $blockTimestamp,
            );
        }

        return $transactions;
    }

    private function weiToEther(string $wei): string
    {
        return bcdiv($wei, '1000000000000000000', 18);
    }
}
