<?php

namespace App\Providers;

use App\Services\CoinGeckoService;
use App\Services\CryptoProviderService;
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
    }
}
