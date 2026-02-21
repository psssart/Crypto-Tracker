<?php

namespace App\Services\WalletHistory;

use App\Contracts\WalletHistoryProvider;
use App\Models\User;
use App\Services\ApiService;
use App\Services\CoinGeckoService;
use App\Services\CryptoProviderService;

class WalletHistoryProviderRegistry
{
    private const PROVIDERS = [
        MoralisHistoryProvider::class,
        BlockchairHistoryProvider::class,
        MempoolSpaceHistoryProvider::class,
        HeliusHistoryProvider::class,
        TronGridHistoryProvider::class,
    ];

    private const PROVIDER_KEY_MAP = [
        MoralisHistoryProvider::class => 'moralis',
        BlockchairHistoryProvider::class => 'blockchair',
        MempoolSpaceHistoryProvider::class => null,
        HeliusHistoryProvider::class => 'helius',
        TronGridHistoryProvider::class => 'trongrid',
    ];

    private const KEY_OPTIONAL = [
        BlockchairHistoryProvider::class,
        MempoolSpaceHistoryProvider::class,
        TronGridHistoryProvider::class,
    ];

    public function __construct(
        private ApiService $api,
        private CoinGeckoService $coinGecko,
        private CryptoProviderService $providerService,
    ) {}

    /**
     * Resolve all available providers for the given network, ordered by priority.
     *
     * @return WalletHistoryProvider[]
     */
    public function resolveAll(string $networkSlug, ?User $user = null): array
    {
        $providers = [];

        foreach (self::PROVIDERS as $providerClass) {
            if (!in_array($networkSlug, $providerClass::supportedNetworks(), true)) {
                continue;
            }

            $providerKey = self::PROVIDER_KEY_MAP[$providerClass];
            $apiKey = $providerKey
                ? $this->providerService->resolveApiKey($providerKey, $user)
                : null;

            if (!$apiKey && !in_array($providerClass, self::KEY_OPTIONAL, true)) {
                continue;
            }

            $providers[] = new $providerClass($this->api, $this->coinGecko, $apiKey);
        }

        return $providers;
    }

    /**
     * Resolve the first (highest-priority) available provider for the given network.
     */
    public function resolve(string $networkSlug, ?User $user = null): ?WalletHistoryProvider
    {
        return $this->resolveAll($networkSlug, $user)[0] ?? null;
    }
}
