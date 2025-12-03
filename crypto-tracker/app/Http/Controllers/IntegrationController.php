<?php

namespace App\Http\Controllers;

use App\Models\UserIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    /**
     * Display the user's integrations page.
     */
    public function index(Request $request): Response
    {
        $integrations = $request->user()
            ->integrations()
            ->orderBy('provider')
            ->get()
            ->map(function (UserIntegration $integration) {
                $plainKey = $integration->api_key;

                return [
                    'id' => $integration->id,
                    'provider' => $integration->provider,
                    'masked_key' => $plainKey
                        ? '****' . mb_substr($plainKey, -4)
                        : null,
                    'settings' => $integration->settings,
                    'last_used_at' => $integration->last_used_at,
                    'revoked_at' => $integration->revoked_at,
                ];
            });

        return Inertia::render('Profile/Integrations', [
            'integrations' => $integrations,
            'status' => session('status'),
        ]);
    }

    /**
     * Store a new integration for the user.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string'],
            'settings' => ['nullable', 'array'],
        ]);

        $request->user()
            ->integrations()
            ->create([
                'provider' => $data['provider'],
                'api_key' => $data['api_key'],
                'settings' => $data['settings'] ?? null,
            ]);

        return Redirect::route('integrations.index')
            ->with('status', 'integration-created');
    }

    /**
     * Update an existing integration.
     */
    public function update(Request $request, UserIntegration $integration): RedirectResponse
    {
        // basic ownership check without policies
        if ($integration->user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'provider' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string'],   // nullable so you can update settings without changing key
            'settings' => ['nullable', 'array'],
        ]);

        $updateData = [
            'provider' => $data['provider'],
            'settings' => $data['settings'] ?? null,
        ];

        if (!empty($data['api_key'])) {
            $updateData['api_key'] = $data['api_key'];
        }

        $integration->update($updateData);

        return Redirect::route('integrations.index')
            ->with('status', 'integration-updated');
    }

    /**
     * Delete an integration.
     */
    public function destroy(Request $request, UserIntegration $integration): RedirectResponse
    {
        if ($integration->user_id !== $request->user()->id) {
            abort(403);
        }

        $integration->delete();

        return Redirect::route('integrations.index')
            ->with('status', 'integration-deleted');
    }
}
