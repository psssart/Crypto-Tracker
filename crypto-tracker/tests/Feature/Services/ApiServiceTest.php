<?php

use App\Exceptions\ApiResponseException;
use App\Services\ApiService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = new ApiService();
});

// ── GET ──────────────────────────────────────────────────────────────

test('get returns response on success', function () {
    Http::fake([
        'example.com/*' => Http::response(['data' => 'ok'], 200),
    ]);

    $response = $this->service->get('https://example.com/api');

    expect($response->json('data'))->toBe('ok');
    expect($response->status())->toBe(200);
});

test('get throws ApiResponseException on HTTP error', function () {
    Http::fake([
        'example.com/*' => Http::response(['error' => 'Not found'], 404),
    ]);

    $this->service->get('https://example.com/api');
})->throws(ApiResponseException::class, 'Not found');

test('get throws ApiResponseException on network error', function () {
    Http::fake([
        'example.com/*' => fn () => throw new \Exception('Connection refused'),
    ]);

    $this->service->get('https://example.com/api');
})->throws(ApiResponseException::class, 'Network error during GET request');

test('get passes query parameters', function () {
    Http::fake([
        'example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $this->service->get('https://example.com/api', ['foo' => 'bar']);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'foo=bar');
    });
});

test('get passes custom headers', function () {
    Http::fake([
        'example.com/*' => Http::response([], 200),
    ]);

    $this->service->get('https://example.com/api', [], ['X-Custom' => 'value']);

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Custom', 'value');
    });
});

// ── POST ─────────────────────────────────────────────────────────────

test('post returns response on success', function () {
    Http::fake([
        'example.com/*' => Http::response(['created' => true], 201),
    ]);

    $response = $this->service->post('https://example.com/api', ['name' => 'test']);

    expect($response->json('created'))->toBeTrue();
});

test('post throws ApiResponseException on HTTP error', function () {
    Http::fake([
        'example.com/*' => Http::response(['message' => 'Validation failed'], 422),
    ]);

    $this->service->post('https://example.com/api', ['bad' => 'data']);
})->throws(ApiResponseException::class, 'Validation failed');

test('post throws ApiResponseException on network error', function () {
    Http::fake([
        'example.com/*' => fn () => throw new \Exception('Timeout'),
    ]);

    $this->service->post('https://example.com/api');
})->throws(ApiResponseException::class, 'Network error during POST request');

// ── Multipart ────────────────────────────────────────────────────────

test('multipart returns response on success', function () {
    Http::fake([
        'example.com/*' => Http::response(['uploaded' => true], 200),
    ]);

    $response = $this->service->multipart('https://example.com/upload', [
        ['name' => 'file', 'contents' => 'data', 'filename' => 'test.txt'],
    ]);

    expect($response->json('uploaded'))->toBeTrue();
});

test('multipart throws ApiResponseException on HTTP error', function () {
    Http::fake([
        'example.com/*' => Http::response(['error' => 'Too large'], 413),
    ]);

    $this->service->multipart('https://example.com/upload', []);
})->throws(ApiResponseException::class, 'Too large');

test('multipart throws ApiResponseException on network error', function () {
    Http::fake([
        'example.com/*' => fn () => throw new \Exception('Connection reset'),
    ]);

    $this->service->multipart('https://example.com/upload', []);
})->throws(ApiResponseException::class, 'Network error during multipart request');

// ── Error message extraction ─────────────────────────────────────────

test('handleResponse extracts error from error key', function () {
    Http::fake([
        'example.com/*' => Http::response(['error' => 'Custom error msg'], 500),
    ]);

    $this->service->get('https://example.com/api');
})->throws(ApiResponseException::class, 'Custom error msg');

test('handleResponse extracts error from message key', function () {
    Http::fake([
        'example.com/*' => Http::response(['message' => 'Custom message'], 500),
    ]);

    $this->service->get('https://example.com/api');
})->throws(ApiResponseException::class, 'Custom message');

test('handleResponse falls back to Unknown API error', function () {
    Http::fake([
        'example.com/*' => Http::response(['something' => 'else'], 500),
    ]);

    $this->service->get('https://example.com/api');
})->throws(ApiResponseException::class, 'Unknown API error');
