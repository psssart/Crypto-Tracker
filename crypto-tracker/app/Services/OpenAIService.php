<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class OpenAIService
{
    public function responses(string $apiKey, string $model, string $input): string
    {
        $res = Http::timeout(30)
            ->withToken($apiKey)
            ->acceptJson()
            ->post('https://api.openai.com/v1/responses', [
                'model' => $model,
                'input' => $input,
                // optional knobs:
                // 'text' => ['verbosity' => 'low'],
            ]);

        $res->throw();

        $json = $res->json();

        // Responses API often includes output_text for convenience (SDKs expose it too),
        // but keep a fallback in case formats shift.
        if (is_string(data_get($json, 'output_text')) && data_get($json, 'output_text') !== '') {
            return $json['output_text'];
        }

        // Fallback: try to extract text chunks from output[]
        $out = data_get($json, 'output', []);
        $text = '';

        foreach ($out as $item) {
            foreach (($item['content'] ?? []) as $c) {
                if (($c['type'] ?? null) === 'output_text' && isset($c['text'])) {
                    $text .= $c['text'];
                }
                if (($c['type'] ?? null) === 'text' && isset($c['text'])) {
                    $text .= $c['text'];
                }
            }
        }

        return trim($text);
    }
}
