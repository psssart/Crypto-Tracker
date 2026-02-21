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
        '0x1'        => 'ethereum',
        '0x89'       => 'polygon',
        '0x38'       => 'bsc',
        '0xa4b1'     => 'arbitrum',
        '0x2105'     => 'base',
        '0xa'        => 'optimism',
        '0xa86a'     => 'avalanche',
        '0xfa'       => 'fantom',
        '0x19'       => 'cronos',
        '0x64'       => 'gnosis',
        '0xe708'     => 'linea',
        '0x2eb'      => 'flow',
        '0x15b38'    => 'chiliz',
        '0x171'      => 'pulsechain',
        '0x531'      => 'sei',
        '0x7e4'      => 'ronin',
        '0x46f'      => 'lisk',
        '0x279f'     => 'monad',
        '0x3e7'      => 'hyperevm',
        '0x2a15c308d' => 'palm',
    ];

    public function verifySignature(Request $request): bool
    {
        $signature = $request->header('x-signature');
        if (! $signature) {
            return false;
        }

        $secret = config('services.moralis.secret_key');
        if (! $secret) {
            return false;
        }

        $rawBody = $request->getContent();
        $expected = Keccak::hash($rawBody . $secret, 256);

        $signature = str_starts_with($signature, '0x') ? substr($signature, 2) : $signature;

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
