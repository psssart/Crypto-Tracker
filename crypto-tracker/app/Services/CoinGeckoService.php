<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CoinGeckoService
{
    private const BASE_URL = 'https://api.coingecko.com/api/v3';
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct(private ?string $apiKey = null)
    {
    }

    public function getPricesUsd(array $coinIds): array
    {
        if (empty($coinIds)) {
            return [];
        }

        $cacheKey = 'coingecko:prices:' . md5(implode(',', $coinIds));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($coinIds) {
            $response = Http::withHeaders($this->headers())
                ->get(self::BASE_URL . '/simple/price', [
                    'ids' => implode(',', $coinIds),
                    'vs_currencies' => 'usd',
                ]);

            $response->throw();

            $data = $response->json();
            $prices = [];

            foreach ($coinIds as $id) {
                $prices[$id] = (float) ($data[$id]['usd'] ?? 0);
            }

            return $prices;
        });
    }

    public function getPriceUsd(string $coinId): float
    {
        $prices = $this->getPricesUsd([$coinId]);

        return $prices[$coinId] ?? 0.0;
    }

    private function headers(): array
    {
        $headers = ['Accept' => 'application/json'];

        if ($this->apiKey) {
            $headers['x-cg-demo-api-key'] = $this->apiKey;
        }

        return $headers;
    }
}
