<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Network;
use App\Models\Wallet;
use Illuminate\Support\Facades\Log;

class WebhookAddressService
{
    private const ALCHEMY_NETWORKS = ['ethereum', 'arbitrum', 'solana', 'polygon', 'base'];

    private const MORALIS_EVM_NETWORKS = [
        'bsc', 'avalanche', 'fantom', 'cronos', 'gnosis', 'optimism',
        'linea', 'flow', 'chiliz', 'pulsechain', 'sei', 'ronin',
        'lisk', 'monad', 'hyperevm', 'palm',
    ];

    public const NON_EVM_NETWORKS = ['bitcoin', 'tron', 'litecoin', 'dogecoin', 'ripple'];

    public function __construct(private ApiService $api)
    {
    }

    public function addressExistsOnNetwork(Network $network, string $address): bool
    {
        $slug = $network->slug;

        // Skip non-EVM networks (different address formats prevent cross-network mistakes)
        if (in_array($slug, self::NON_EVM_NETWORKS, true) || $slug === 'solana') {
            return true;
        }

        $apiKey = config('services.moralis.api_key');

        // Fail-open if no API key or no chain_id
        if (!$apiKey || !$network->chain_id) {
            return true;
        }

        try {
            $chainHex = '0x' . dechex($network->chain_id);
            $headers = ['X-API-Key' => $apiKey];
            $address = strtolower($address);

            // Check native balance
            $balanceResponse = $this->api->get(
                "https://deep-index.moralis.io/api/v2.2/{$address}/balance",
                ['chain' => $chainHex],
                $headers,
            );

            $balance = $balanceResponse->json('balance') ?? '0';

            if ($balance !== '0') {
                return true;
            }

            // Balance is zero — check for any transaction history
            $txResponse = $this->api->get(
                "https://deep-index.moralis.io/api/v2.2/{$address}",
                ['chain' => $chainHex, 'limit' => 1],
                $headers,
            );

            $results = $txResponse->json('result') ?? [];

            return count($results) > 0;
        } catch (\Throwable $e) {
            Log::warning('WebhookAddressService: address existence check failed, allowing through', [
                'network' => $slug,
                'address' => $address,
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }

    public function addAddress(Wallet $wallet): void
    {
        $slug = $wallet->network->slug;

        if (in_array($slug, self::ALCHEMY_NETWORKS, true)) {
            $this->alchemyUpdate($wallet, 'add');
        } elseif (in_array($slug, self::NON_EVM_NETWORKS, true)) {
            Log::warning('WebhookAddressService: non-EVM network not supported for webhook streams', [
                'network' => $slug,
                'address' => $wallet->address,
            ]);
        } else {
            $this->moralisUpdate($wallet, 'add');
        }
    }

    public function removeAddress(Wallet $wallet): void
    {
        $slug = $wallet->network->slug;

        if (in_array($slug, self::ALCHEMY_NETWORKS, true)) {
            $this->alchemyUpdate($wallet, 'remove');
        } elseif (in_array($slug, self::NON_EVM_NETWORKS, true)) {
            Log::warning('WebhookAddressService: non-EVM network not supported for webhook streams', [
                'network' => $slug,
                'address' => $wallet->address,
            ]);
        } else {
            $this->moralisUpdate($wallet, 'remove');
        }
    }

    private function alchemyUpdate(Wallet $wallet, string $action): void
    {
        $authToken = config('services.alchemy.auth_token');
        $webhookId = config("services.alchemy.webhooks.{$wallet->network->slug}.id");

        if (!$authToken || !$webhookId) {
            Log::warning('WebhookAddressService: Alchemy config missing, skipping', [
                'network' => $wallet->network->slug,
                'has_auth_token' => (bool) $authToken,
                'has_webhook_id' => (bool) $webhookId,
            ]);
            return;
        }

        $body = ['webhook_id' => $webhookId];

        if ($action === 'add') {
            $body['addresses_to_add'] = [$wallet->address];
            $body['addresses_to_remove'] = [];
        } else {
            $body['addresses_to_add'] = [];
            $body['addresses_to_remove'] = [$wallet->address];
        }

        $this->api->patch(
            'https://dashboard.alchemy.com/api/update-webhook-addresses',
            $body,
            ['X-Alchemy-Token' => $authToken],
        );

        Log::info('Alchemy addresses updated', [
            'action' => $action,
            'network' => $wallet->network->slug,
            'address' => $wallet->address,
        ]);
    }

    private function moralisUpdate(Wallet $wallet, string $action): void
    {
        $apiKey = config('services.moralis.api_key');
        $streamId = config('services.moralis.stream_id');

        if (!$apiKey || !$streamId) {
            Log::warning('WebhookAddressService: Moralis config missing, skipping', [
                'network' => $wallet->network->slug,
                'has_api_key' => (bool) $apiKey,
                'has_stream_id' => (bool) $streamId,
            ]);
            return;
        }

        $url = "https://api.moralis-streams.com/streams/evm/{$streamId}/address";
        $headers = ['X-API-Key' => $apiKey];
        $body = ['address' => $wallet->address];

        if ($action === 'add') {
            $this->api->post($url, $body, $headers);
            Log::info('Moralis address added', [
                'network' => $wallet->network->slug,
                'address' => $wallet->address,
            ]);
        } else {
            $this->api->delete($url, $body, $headers);
            Log::info('Moralis address removed', [
                'network' => $wallet->network->slug,
                'address' => $wallet->address,
            ]);
        }
    }
}
