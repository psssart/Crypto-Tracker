<?php

namespace App\Http\Controllers;

use App\Models\UserIntegration;
use App\Services\IntegrationHealthService;
use App\Support\IntegrationRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChartController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $providers = IntegrationRegistry::all();
        $integrations = $user->integrations()->get()->keyBy('provider');

        $integrationBackedSourceIds = [];

        foreach ($providers as $config) {
            if (!empty($config['ws_source_id'])) {
                $integrationBackedSourceIds[] = $config['ws_source_id'];
            }
        }

        $sourceAuth = [];
        foreach ($providers as $providerKey => $config) {
            $wsId = $config['ws_source_id'] ?? null;
            if (!$wsId) {
                continue;
            }

            $integrationBackedSourceIds[] = $wsId;

            $integration = $integrations->get($providerKey);
            if (!$integration || !$integration->api_key) {
                continue;
            }

            if ($providerKey === 'alltick') {
                $sourceAuth[$wsId] = [
                    'token' => $integration->api_key,
                ];
            } elseif ($providerKey === 'freecryptoapi') {
                $sourceAuth[$wsId] = [
                    'apiKey' => $integration->api_key,
                ];
            } elseif ($providerKey === 'bybit') {
                $settings = $integration->settings ?? [];
                $sourceAuth[$wsId] = [
                    'apiKey' => $integration->api_key,
                    'apiSecret' => $settings['api_secret'] ?? null,
                ];
            }
        }

        return Inertia::render('Chart', [
            'integrationBackedSourceIds' => $integrationBackedSourceIds,
            'integrationsUrl' => route('integrations.index'),
            'sourceAuth' => $sourceAuth,
        ]);
    }

    /**
     * Check if the current user can use a given chart source (based on stored integration).
     */
    public function checkSource(
        Request $request,
        IntegrationHealthService $health
    ): JsonResponse {
        $data = $request->validate([
            'ws_source_id' => ['required', 'string'],
        ]);

        $wsSourceId = $data['ws_source_id'];

        // Find which provider is behind this websocket source
        $providers = IntegrationRegistry::all();

        $providerKey = null;
        $providerConfig = null;

        foreach ($providers as $key => $config) {
            if (($config['ws_source_id'] ?? null) === $wsSourceId) {
                $providerKey = $key;
                $providerConfig = $config;
                break;
            }
        }

        if (!$providerKey) {
            return response()->json([
                'ok' => false,
                'message' => 'No integration is associated with this data source.',
                'ws_source_id' => $wsSourceId,
            ], 422);
        }

        /** @var UserIntegration|null $integration */
        $integration = $request->user()
            ->integrations()
            ->where('provider', $providerKey)
            ->first();

        if (!$integration || empty($integration->api_key)) {
            return response()->json([
                'ok' => false,
                'message' => ($providerConfig['name'] ?? $providerKey)
                    .' integration is not configured.',
                'ws_source_id' => $wsSourceId,
            ]);
        }

        $settings = $integration->settings ?? [];
        $apiSecret = $settings['api_secret'] ?? null;

        $result = $health->check(
            $providerKey,
            $integration->api_key,
            $apiSecret,
        );

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'ws_source_id' => $wsSourceId,
            'provider' => $providerKey,
        ]);
    }
}
