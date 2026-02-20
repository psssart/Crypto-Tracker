<?php

namespace App\Providers;

use App\Services\CoinGeckoService;
use App\Services\CryptoProviderService;
use App\Services\WalletHistory\WalletHistoryProviderRegistry;
use App\Services\WebhookAddressService;
use Illuminate\Support\ServiceProvider;

class CryptoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CoinGeckoService::class, function ($app) {
            return new CoinGeckoService(
                apiKey: config('services.coingecko.key'),
            );
        });

        $this->app->singleton(CryptoProviderService::class);

        $this->app->singleton(WalletHistoryProviderRegistry::class);

        $this->app->singleton(WebhookAddressService::class);
    }
}
