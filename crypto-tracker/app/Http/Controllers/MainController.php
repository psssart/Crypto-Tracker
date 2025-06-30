<?php

namespace App\Http\Controllers;

use App\Services\ApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MainController extends Controller
{
    protected ApiService $apiService;

    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Display the user's profile form.
     */
    public function getLatestTokenProfiles(Request $request): JsonResponse
    {
        $response = $this->apiService->get('https://api.dexscreener.com/token-profiles/latest/v1');

        if ($response->getStatusCode() == 200) {
            return $response->json();
        } else {
            return response()->json($response->getStatusCode(), $response->getHeaders());
        }
    }
}
