<?php

namespace App\Services\Webhooks;

use App\Contracts\CryptoWebhookHandler;
use App\DTOs\ParsedTransaction;
use Illuminate\Http\Request;

class AlchemyWebhookHandler implements CryptoWebhookHandler
{
    private const NETWORK_MAP = [
        'ETH_MAINNET'   => 'ethereum',
        'ARB_MAINNET'   => 'arbitrum',
        'MATIC_MAINNET' => 'polygon',
        'BASE_MAINNET'  => 'base',
        'SOL_MAINNET'   => 'solana',
    ];

    public function verifySignature(Request $request): bool
    {
        $signature = $request->header('x-alchemy-signature');
        if (! $signature) {
            return false;
        }

        $signingKey = config('services.alchemy.auth_token');
        if (! $signingKey) {
            return false;
        }

        $rawBody = $request->getContent();
        $expected = hash_hmac('sha256', $rawBody, $signingKey);

        return hash_equals($expected, $signature);
    }

    /** @return ParsedTransaction[] */
    public function parseTransactions(array $payload): array
    {
        $event = $payload['event'] ?? [];
        $networkString = $event['network'] ?? null;
        $networkSlug = self::NETWORK_MAP[$networkString] ?? null;

        if (! $networkSlug) {
            return [];
        }

        $transactions = [];

        foreach ($event['activity'] ?? [] as $activity) {
            if (($activity['category'] ?? '') !== 'external') {
                continue;
            }

            $from = strtolower($activity['fromAddress'] ?? '');
            $to = strtolower($activity['toAddress'] ?? '');
            $hash = $activity['hash'] ?? null;
            $value = (string) ($activity['value'] ?? '0');
            $blockNum = isset($activity['blockNum'])
                ? (int) hexdec($activity['blockNum'])
                : null;

            if (! $hash || (! $from && ! $to)) {
                continue;
            }

            $transactions[] = new ParsedTransaction(
                networkSlug: $networkSlug,
                txHash: $hash,
                fromAddress: $from,
                toAddress: $to,
                amount: $value,
                blockNumber: $blockNum,
                minedAt: null,
            );
        }

        return $transactions;
    }
}
