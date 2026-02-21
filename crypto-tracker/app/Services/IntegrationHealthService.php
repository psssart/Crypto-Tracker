<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IntegrationHealthService
{
    /**
     * Generic entrypoint: check provider credentials.
     */
    public function check(string $providerKey, string $apiKey, ?string $apiSecret = null): array
    {
        try {
            return match ($providerKey) {
                'alltick'       => $this->checkAllTick($apiKey),
                'freecryptoapi' => $this->checkFreeCryptoApi($apiKey),
                'bybit'         => $this->checkBybit($apiKey, $apiSecret),
                'openai'        => $this->checkOpenAI($apiKey),
                'moralis'       => $this->checkMoralis($apiKey),
                'alchemy'       => $this->checkAlchemy($apiKey),
                'etherscan'     => $this->checkEtherscan($apiKey),
                'trongrid'      => $this->checkTronGrid($apiKey),
                'helius'        => $this->checkHelius($apiKey),
                'blockchair'    => $this->checkBlockchair($apiKey),
                'coingecko'     => $this->checkCoinGecko($apiKey),
                default         => [
                    'ok' => false,
                    'message' => 'Health check not implemented for this provider.',
                ],
            };
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Health check error: '.$e->getMessage(),
            ];
        }
    }

    protected function checkAllTick(string $token): array
    {
        $query = [
            'trace' => (string) Str::uuid(),
            'data' => [
                'code' => 'BTCUSDT',
                'kline_type' => 1,
                'kline_timestamp_end' => 0,
                'query_kline_num' => 1,
                'adjust_type' => 0,
            ],
        ];

        $response = Http::timeout(5)->get(
            'https://quote.alltick.co/quote-b-api/kline',
            [
                'token' => $token,
                'query' => json_encode($query),
            ]
        );

        if (!$response->ok()) {
            return [
                'ok' => false,
                'message' => 'AllTick did not respond with 200.',
            ];
        }

        $json = $response->json();

        if (!isset($json['ret']) || (int) $json['ret'] !== 200) {
            return [
                'ok' => false,
                'message' => 'AllTick responded with error code.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'AllTick token is valid.',
        ];
    }

    protected function checkFreeCryptoApi(string $apiKey): array
    {
        $response = Http::timeout(5)
            ->withToken($apiKey)
            ->get('https://api.freecryptoapi.com/v1/getCryptoList');

        if (!$response->ok()) {
            return [
                'ok' => false,
                'message' => 'FreeCryptoAPI did not respond with 200.',
            ];
        }

        $json = $response->json();

        if (empty($json)) {
            return [
                'ok' => false,
                'message' => 'FreeCryptoAPI response is empty.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'FreeCryptoAPI key is valid.',
        ];
    }

    protected function checkBybit(string $apiKey, ?string $apiSecret): array
    {
        if (empty($apiSecret)) {
            return [
                'ok' => false,
                'message' => 'Bybit API secret is missing.',
            ];
        }

        $timestamp = (string) (int) (microtime(true) * 1000);
        $recvWindow = '5000';
        $body = '';

        $preSign = $timestamp . $apiKey . $recvWindow . $body;

        $signature = hash_hmac('sha256', $preSign, $apiSecret);

        $response = Http::timeout(5)
            ->withHeaders([
                'X-BAPI-API-KEY'     => $apiKey,
                'X-BAPI-TIMESTAMP'   => $timestamp,
                'X-BAPI-RECV-WINDOW' => $recvWindow,
                'X-BAPI-SIGN'        => $signature,
            ])
            ->get('https://api.bybit.com/v5/user/query-api');

        if (!$response->ok()) {
            return [
                'ok' => false,
                'message' => 'Bybit did not respond with 200.',
            ];
        }

        $json = $response->json();

        if (!isset($json['retCode']) || (int) $json['retCode'] !== 0) {
            return [
                'ok' => false,
                'message' => 'Bybit reported API key error.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'Bybit API key & secret are valid.',
        ];
    }

    private function checkOpenAI(string $apiKey): array
    {
        $res = Http::timeout(10)
            ->withToken($apiKey)
            ->acceptJson()
            ->get('https://api.openai.com/v1/models');

        if ($res->successful()) {
            return ['ok' => true, 'message' => 'OpenAI API key looks valid.'];
        }

        $status = $res->status();
        $msg = 'OpenAI health check failed.';

        if ($status === 401) {
            $msg = 'OpenAI rejected the API key (401). Check the key / project access.';
        }

        return [
            'ok' => false,
            'message' => $msg,
            'details' => [
                'status' => $status,
            ],
        ];
    }

    private function checkMoralis(string $apiKey): array
    {
        $res = Http::timeout(5)
            ->withHeaders(['X-API-Key' => $apiKey])
            ->acceptJson()
            ->get('https://deep-index.moralis.io/api/v2.2/web3/version');

        if ($res->successful()) {
            return ['ok' => true, 'message' => 'Moralis API key is valid.'];
        }

        if ($res->status() === 401) {
            return ['ok' => false, 'message' => 'Moralis rejected the API key (401).'];
        }

        return ['ok' => false, 'message' => 'Moralis health check failed (HTTP '.$res->status().').'];
    }

    private function checkAlchemy(string $apiKey): array
    {
        $res = Http::timeout(5)
            ->acceptJson()
            ->post('https://eth-mainnet.g.alchemy.com/v2/'.$apiKey, [
                'jsonrpc' => '2.0',
                'method' => 'web3_clientVersion',
                'params' => [],
                'id' => 1,
            ]);

        if (! $res->successful()) {
            if ($res->status() === 401 || $res->status() === 403) {
                return ['ok' => false, 'message' => 'Alchemy rejected the API key.'];
            }

            return ['ok' => false, 'message' => 'Alchemy health check failed (HTTP '.$res->status().').'];
        }

        $json = $res->json();

        if (isset($json['error'])) {
            return ['ok' => false, 'message' => 'Alchemy RPC error: '.($json['error']['message'] ?? 'unknown')];
        }

        return ['ok' => true, 'message' => 'Alchemy API key is valid.'];
    }

    private function checkEtherscan(string $apiKey): array
    {
        $res = Http::timeout(5)
            ->acceptJson()
            ->get('https://api.etherscan.io/api', [
                'module' => 'stats',
                'action' => 'ethprice',
                'apikey' => $apiKey,
            ]);

        if (! $res->successful()) {
            return ['ok' => false, 'message' => 'Etherscan health check failed (HTTP '.$res->status().').'];
        }

        $json = $res->json();

        if (($json['status'] ?? '') !== '1') {
            $msg = $json['result'] ?? 'Unknown error';

            return ['ok' => false, 'message' => 'Etherscan error: '.$msg];
        }

        return ['ok' => true, 'message' => 'Etherscan API key is valid.'];
    }

    private function checkTronGrid(string $apiKey): array
    {
        $res = Http::timeout(5)
            ->withHeaders(['TRON-PRO-API-KEY' => $apiKey])
            ->acceptJson()
            ->get('https://api.trongrid.io/v1/blocks/latest');

        if ($res->successful()) {
            return ['ok' => true, 'message' => 'TronGrid API key is valid.'];
        }

        if ($res->status() === 401 || $res->status() === 403) {
            return ['ok' => false, 'message' => 'TronGrid rejected the API key.'];
        }

        return ['ok' => false, 'message' => 'TronGrid health check failed (HTTP '.$res->status().').'];
    }

    private function checkHelius(string $apiKey): array
    {
        $res = Http::timeout(5)
            ->acceptJson()
            ->post('https://mainnet.helius-rpc.com/?api-key='.$apiKey, [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'getHealth',
            ]);

        if (! $res->successful()) {
            if ($res->status() === 401 || $res->status() === 403) {
                return ['ok' => false, 'message' => 'Helius rejected the API key.'];
            }

            return ['ok' => false, 'message' => 'Helius health check failed (HTTP '.$res->status().').'];
        }

        $json = $res->json();

        if (isset($json['error'])) {
            return ['ok' => false, 'message' => 'Helius RPC error: '.($json['error']['message'] ?? 'unknown')];
        }

        return ['ok' => true, 'message' => 'Helius API key is valid.'];
    }

    private function checkBlockchair(string $apiKey): array
    {
        $res = Http::timeout(5)
            ->acceptJson()
            ->get('https://api.blockchair.com/bitcoin/stats', [
                'key' => $apiKey,
            ]);

        if (! $res->successful()) {
            if ($res->status() === 401 || $res->status() === 403) {
                return ['ok' => false, 'message' => 'Blockchair rejected the API key.'];
            }

            return ['ok' => false, 'message' => 'Blockchair health check failed (HTTP '.$res->status().').'];
        }

        $json = $res->json();

        if (isset($json['context']['error'])) {
            return ['ok' => false, 'message' => 'Blockchair error: '.$json['context']['error']];
        }

        return ['ok' => true, 'message' => 'Blockchair API key is valid.'];
    }

    private function checkCoinGecko(string $apiKey): array
    {
        // Try Demo API first (free tier), fall back to Pro API
        $res = Http::timeout(5)
            ->withHeaders(['x-cg-demo-api-key' => $apiKey])
            ->acceptJson()
            ->get('https://api.coingecko.com/api/v3/ping');

        if ($res->successful()) {
            return ['ok' => true, 'message' => 'CoinGecko API key is valid (Demo).'];
        }

        // If Demo fails with auth error, try Pro endpoint
        if ($res->status() === 401 || $res->status() === 403) {
            $proRes = Http::timeout(5)
                ->withHeaders(['x-cg-pro-api-key' => $apiKey])
                ->acceptJson()
                ->get('https://pro-api.coingecko.com/api/v3/ping');

            if ($proRes->successful()) {
                return ['ok' => true, 'message' => 'CoinGecko API key is valid (Pro).'];
            }

            return ['ok' => false, 'message' => 'CoinGecko rejected the API key on both Demo and Pro endpoints.'];
        }

        return ['ok' => false, 'message' => 'CoinGecko health check failed (HTTP '.$res->status().').'];
    }
}
