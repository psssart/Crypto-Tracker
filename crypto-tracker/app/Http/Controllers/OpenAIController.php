<?php

namespace App\Http\Controllers;

use App\Services\OpenAIService;
use Illuminate\Http\Request;

class OpenAIController extends Controller
{
    public function respond(Request $request, OpenAIService $openai)
    {
        $data = $request->validate([
            'input' => ['required', 'string', 'max:8000'],
            'model' => ['nullable', 'string'],
        ]);

        $integration = $request->user()
            ->integrations()
            ->where('provider', 'openai')
            ->first();

        if (!$integration || !$integration->api_key) {
            return response()->json([
                'ok' => false,
                'message' => 'No OpenAI integration configured for this user.',
            ], 422);
        }

        $model = $data['model']
            ?? data_get($integration->settings, 'model')
            ?? config('integrations.providers.openai.default_settings.model', 'gpt-4o-mini');

        $text = $openai->responses($integration->api_key, $model, $data['input']);

        $integration->forceFill(['last_used_at' => now()])->save();

        return response()->json([
            'ok' => true,
            'text' => $text,
            'model' => $model,
        ]);
    }
}
