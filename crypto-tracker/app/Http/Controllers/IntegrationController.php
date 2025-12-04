<?php

namespace App\Http\Controllers;

use App\Models\UserIntegration;
use App\Support\IntegrationRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $providers = IntegrationRegistry::all();

        $integrations = $request->user()
            ->integrations()
            ->orderBy('provider')
            ->get()
            ->map(function (UserIntegration $integration) use ($providers) {
                $plainKey = $integration->api_key;
                $providerConfig = $providers[$integration->provider] ?? null;

                return [
                    'id' => $integration->id,
                    'provider' => $integration->provider,
                    'provider_name' => $providerConfig['name'] ?? $integration->provider,
                    'masked_key' => $plainKey ? '****' . mb_substr($plainKey, -4) : null,
                    'has_api_key' => !empty($plainKey),
                    'last_used_at' => $integration->last_used_at,
                    'revoked_at' => $integration->revoked_at,
                ];
            });

        return Inertia::render('Profile/Integrations', [
            'providers' => $providers,
            'integrations' => $integrations,
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $providerKeys = IntegrationRegistry::keys();

        $data = $request->validate([
            'provider'   => ['required', 'string', Rule::in($providerKeys)],
            'api_key'    => ['required', 'string'],
            'api_secret' => ['nullable', 'string'],
        ]);

        $settings = [];

        if ($data['provider'] === 'bybit' && !empty($data['api_secret'])) {
            $settings['api_secret'] = $data['api_secret'];
        }

        $request->user()
            ->integrations()
            ->updateOrCreate(
                ['provider' => $data['provider']],
                [
                    'api_key'  => $data['api_key'],
                    'settings' => !empty($settings) ? $settings : null,
                ]
            );

        return Redirect::route('integrations.index')
            ->with('status', 'integration-saved');
    }

    public function update(Request $request, UserIntegration $integration): RedirectResponse
    {
        if ($integration->user_id !== $request->user()->id) {
            abort(403);
        }

        $providerKeys = IntegrationRegistry::keys();

        $data = $request->validate([
            'provider'   => ['required', 'string', Rule::in($providerKeys)],
            'api_key'    => ['nullable', 'string'],
            'api_secret' => ['nullable', 'string'],
        ]);

        $updateData = [
            'provider' => $data['provider'],
        ];

        if (!empty($data['api_key'])) {
            $updateData['api_key'] = $data['api_key'];
        }

        $settings = $integration->settings ?? [];

        if ($data['provider'] === 'bybit' && !empty($data['api_secret'])) {
            $settings['api_secret'] = $data['api_secret'];
        }

        if (!empty($settings)) {
            $updateData['settings'] = $settings;
        }

        $integration->update($updateData);

        return Redirect::route('integrations.index')
            ->with('status', 'integration-updated');
    }

    public function destroy(Request $request, UserIntegration $integration): RedirectResponse
    {
        if ($integration->user_id !== $request->user()->id) {
            abort(403);
        }

        $integration->delete();

        return Redirect::route('integrations.index')
            ->with('status', 'integration-deleted');
    }

    /**
     * Check if provided API key works for given provider.
     */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string'],
            'api_key'  => ['required', 'string'],
            'api_secret' => ['nullable', 'string'],
        ]);

        $providerKey = $data['provider'];
        $apiKey = $data['api_key'];
        $apiSecret = $data['api_secret'] ?? null;

        $providerConfig = IntegrationRegistry::get($providerKey);

        if (!$providerConfig) {
            return response()->json([
                'ok' => false,
                'message' => 'Unknown integration provider.',
            ], 422);
        }

        if (empty($providerConfig['health_check']['enabled'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Health check is not available for this provider.',
            ], 422);
        }

        try {
            $ok = match ($providerKey) {
                'alltick'       => $this->checkAllTick($apiKey),
                'freecryptoapi' => $this->checkFreeCryptoApi($apiKey),
                'bybit'         => $this->checkBybit($apiKey, $apiSecret),
                default         => false,
            };

            if (!$ok) {
                return response()->json([
                    'ok' => false,
                    'message' => 'API key is invalid or the provider is not responding.',
                ], 200);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Connection successful.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Health check failed: '.$e->getMessage(),
            ], 200);
        }
    }

    protected function checkAllTick(string $apiKey): bool
    {
        //dd($apiKey);
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
                'token' => $apiKey,
                'query' => json_encode($query),
            ]
        );

        if (!$response->ok()) {
            return false;
        }

        $json = $response->json();

        return isset($json['ret']) && (int) $json['ret'] === 200;
    }

    protected function checkFreeCryptoApi(string $apiKey): bool
    {
        $response = Http::timeout(5)
            ->withToken($apiKey)
            ->get('https://api.freecryptoapi.com/v1/getCryptoList');

        if (!$response->ok()) {
            return false;
        }

        $json = $response->json();

        return !empty($json);
    }

    protected function checkBybit(string $apiKey, ?string $apiSecret): bool
    {
        if (empty($apiSecret)) {
            return false;
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
                // 'X-BAPI-SIGN-TYPE' => '2', // optional
            ])
            ->get('https://api.bybit.com/v5/user/query-api');

        if (!$response->ok()) {
            return false;
        }

        $json = $response->json();

        // v5 uses retCode/retMsg format; success is retCode === 0
        return isset($json['retCode']) && (int) $json['retCode'] === 0;
    }
}
