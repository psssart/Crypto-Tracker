<?php

namespace App\Services;

use App\Models\User;

class CryptoProviderService
{
    private const KEY_CONFIG_MAP = [
        'moralis' => 'services.moralis.api_key',
        'alchemy' => 'services.alchemy.key',
        'coingecko' => 'services.coingecko.key',
        'etherscan' => 'services.etherscan.key',
        'trongrid' => 'services.trongrid.key',
        'helius' => 'services.helius.key',
        'blockchair' => 'services.blockchair.key',
    ];

    public function resolveApiKey(string $provider, ?User $user = null): ?string
    {
        // Strategy 1: Check user's stored integration key
        if ($user) {
            $integration = $user->integrations()
                ->where('provider', $provider)
                ->whereNull('revoked_at')
                ->first();

            if ($integration && $integration->api_key) {
                return $integration->api_key;
            }
        }

        // Strategy 2: Fall back to environment config
        $configPath = self::KEY_CONFIG_MAP[$provider] ?? "services.{$provider}.key";

        return config($configPath);
    }
}
