<?php

namespace App\Http\Controllers;

use App\Models\UserIntegration;
use App\Support\IntegrationRegistry;
use App\Services\IntegrationHealthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
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

    public function check(Request $request, IntegrationHealthService $health): JsonResponse
    {
        $data = $request->validate([
            'provider'    => ['required', 'string'],
            'api_key'     => ['required', 'string'],
            'api_secret'  => ['nullable', 'string'],
        ]);

        $providerKey = $data['provider'];
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
                'message' => 'Health check is not enabled for this provider.',
            ], 422);
        }

        $result = $health->check(
            $providerKey,
            $data['api_key'],
            $data['api_secret'] ?? null,
        );

        return response()->json($result);
    }

}
