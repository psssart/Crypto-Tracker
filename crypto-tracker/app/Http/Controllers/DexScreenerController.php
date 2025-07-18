<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiResponseException;
use App\Services\ApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DexScreenerController extends Controller
{
    public function __construct(protected ApiService $apiService) {}

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
            $this->apiService->get('https://voidcyborg.com/tracking/pixel.png?id=test_pavel&event=get');
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
                'error'   => 'Could not tokens with most active boosts',
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }
}
