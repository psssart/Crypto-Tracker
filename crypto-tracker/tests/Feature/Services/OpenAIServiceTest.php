<?php

use App\Services\OpenAIService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = new OpenAIService();
});

test('responses returns output_text when present', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'output_text' => 'Hello from GPT!',
            'output' => [],
        ], 200),
    ]);

    $result = $this->service->responses('sk-key', 'gpt-4o-mini', 'Hi');

    expect($result)->toBe('Hello from GPT!');
});

test('responses extracts text from output content as fallback', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'output' => [
                [
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Part 1'],
                        ['type' => 'text', 'text' => ' Part 2'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = $this->service->responses('sk-key', 'gpt-4o-mini', 'Hi');

    expect($result)->toBe('Part 1 Part 2');
});

test('responses returns empty string when no content found', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'output' => [],
        ], 200),
    ]);

    $result = $this->service->responses('sk-key', 'gpt-4o-mini', 'Hi');

    expect($result)->toBe('');
});

test('responses throws on API error', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'Invalid key'], 401),
    ]);

    $this->service->responses('sk-bad', 'gpt-4o-mini', 'Hi');
})->throws(RequestException::class);

test('responses sends correct request', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['output_text' => 'ok'], 200),
    ]);

    $this->service->responses('sk-test', 'gpt-4o', 'What is 2+2?');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/responses'
            && $request->hasHeader('Authorization', 'Bearer sk-test')
            && $request['model'] === 'gpt-4o'
            && $request['input'] === 'What is 2+2?';
    });
});
