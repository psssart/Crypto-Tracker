<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCryptoWebhook;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $log = WebhookLog::create([
            'payload' => $request->all(),
        ]);

        ProcessCryptoWebhook::dispatch($log);

        return response()->json(['status' => 'accepted'], 202);
    }
}
