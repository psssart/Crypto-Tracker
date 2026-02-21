<?php

namespace App\Http\Controllers;

use App\Contracts\CryptoWebhookHandler;
use App\Jobs\ProcessCryptoWebhook;
use App\Models\WebhookLog;
use App\Services\Webhooks\AlchemyWebhookHandler;
use App\Services\Webhooks\MoralisWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CryptoWebhookController extends Controller
{
    public function handleMoralis(Request $request): JsonResponse
    {
        return $this->process($request, app(MoralisWebhookHandler::class), 'moralis');
    }

    public function handleAlchemy(Request $request): JsonResponse
    {
        return $this->process($request, app(AlchemyWebhookHandler::class), 'alchemy');
    }

    private function process(Request $request, CryptoWebhookHandler $handler, string $source): JsonResponse
    {
        if (! $handler->verifySignature($request)) {
            Log::warning('Webhook signature verification failed', ['source' => $source]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $log = WebhookLog::create([
            'source' => $source,
            'payload' => $request->all(),
        ]);

        ProcessCryptoWebhook::dispatch($log);

        return response()->json(['status' => 'accepted'], 202);
    }
}
