<?php

use App\Services\CoinGeckoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('getPricesUsd fetches and caches prices', function () {
    Http::fake([
        'api.coingecko.com/*' => Http::response([
            'bitcoin' => ['usd' => 50000.0],
            'ethereum' => ['usd' => 3000.0],
        ]),
    ]);

    $service = new CoinGeckoService('test-key');
    $prices = $service->getPricesUsd(['bitcoin', 'ethereum']);

    expect($prices)->toBe([
        'bitcoin' => 50000.0,
        'ethereum' => 3000.0,
    ]);

    Http::assertSentCount(1);

    // Second call should use cache
    $cached = $service->getPricesUsd(['bitcoin', 'ethereum']);
    expect($cached)->toBe($prices);
    Http::assertSentCount(1); // no additional request
});

test('getPriceUsd returns single coin price', function () {
    Http::fake([
        'api.coingecko.com/*' => Http::response([
            'bitcoin' => ['usd' => 42000.50],
        ]),
    ]);

    $service = new CoinGeckoService('test-key');
    $price = $service->getPriceUsd('bitcoin');

    expect($price)->toBe(42000.50);
});

test('getPricesUsd handles API failure', function () {
    Http::fake([
        'api.coingecko.com/*' => Http::response('Rate limited', 429),
    ]);

    $service = new CoinGeckoService('test-key');

    expect(fn () => $service->getPricesUsd(['bitcoin']))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);
});
