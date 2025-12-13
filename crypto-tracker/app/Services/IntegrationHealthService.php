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
                'openai' => $this->checkOpenAI($apiKey),
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
}
