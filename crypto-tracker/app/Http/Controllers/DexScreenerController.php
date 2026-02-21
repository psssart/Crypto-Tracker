<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiResponseException;
use App\Models\Network;
use App\Services\ApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DexScreenerController extends Controller
{
    public function __construct(protected ApiService $apiService) {}

    public function index(Request $request): \Inertia\Response
    {
        $networks = Network::where('is_active', true)->pluck('id', 'slug');

        $trackedAddresses = [];
        if ($user = $request->user()) {
            $trackedAddresses = $user->wallets()->with('network')->get()
                ->map(fn ($w) => ['network_slug' => $w->network->slug, 'address' => $w->address])
                ->all();
        }

        return Inertia::render('Dashboard', [
            'supportedNetworkMap' => $networks,
            'trackedAddresses' => $trackedAddresses,
        ]);
    }

    /**
     * Fetch and return the latest token profiles.
     *
     * @param Request $request
     * @param int|null $group Optional flag (1 = group by chainId)
     * @return JsonResponse
     */
    public function getLatestTokenProfiles(Request $request, ?int $group = null): JsonResponse
    {
        try {
            $response = $this->apiService->get('https://api.dexscreener.com/token-profiles/latest/v1');

            $profiles = $response->json();

            if ($group === 1) {
                $profiles = collect($profiles)
                    ->groupBy('chainId')
                    ->map(fn ($items) => $items->all())
                    ->all();
            }

            return response()->json($profiles, 200);
        } catch (ApiResponseException $e) {
            return response()->json([
                'error'   => 'Could not fetch latest token profiles',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Fetch and return the latest boosted tokens.
     *
     * @param Request $request
     * @param int|null $group Optional flag (1 = group by chainId)
     * @return JsonResponse
     */
    public function getLatestBoostedTokens(Request $request, ?int $group = null): JsonResponse
    {
        try {
            $response = $this->apiService->get('https://api.dexscreener.com/token-boosts/latest/v1');

            $profiles = $response->json();

            if ($group === 1) {
                $profiles = collect($profiles)
                    ->groupBy('chainId')
                    ->map(fn ($items) => $items->all())
                    ->all();
            }

            return response()->json($profiles, 200);
        } catch (ApiResponseException $e) {
            return response()->json([
                'error'   => 'Could not fetch latest boosted tokens',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }

    /**
     * Fetch and return tokens with most active boosts.
     *
     * @param Request $request
     * @param int|null $group Optional flag (1 = group by chainId)
     * @return JsonResponse
     */
    public function getMostBoostedTokens(Request $request, ?int $group = null): JsonResponse
    {
        try {
            $response = $this->apiService->get('https://api.dexscreener.com/token-boosts/top/v1');

            $profiles = $response->json();

            if ($group === 1) {
                $profiles = collect($profiles)
                    ->groupBy('chainId')
                    ->map(fn ($items) => $items->all())
                    ->all();
            }

            return response()->json($profiles, 200);
        } catch (ApiResponseException $e) {
            return response()->json([
                'error'   => 'Could not fetch tokens with most active boosts',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }
}
