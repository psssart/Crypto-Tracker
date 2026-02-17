<?php

namespace App\Services;

use App\Models\User;

class CryptoProviderService
{
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
        if ($provider === 'moralis') {
            return config('services.moralis.api_key');
        }

        return config("services.{$provider}.key");
    }
}
