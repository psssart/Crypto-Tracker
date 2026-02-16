<?php

use App\Services\IntegrationHealthService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = new IntegrationHealthService();
});

// ── AllTick ──────────────────────────────────────────────────────────

test('checkAllTick returns ok for valid token', function () {
    Http::fake([
        'quote.alltick.co/*' => Http::response(['ret' => 200], 200),
    ]);

    $result = $this->service->check('alltick', 'valid-token');

    expect($result['ok'])->toBeTrue();
    expect($result['message'])->toBe('AllTick token is valid.');
});

test('checkAllTick returns error for invalid token', function () {
    Http::fake([
        'quote.alltick.co/*' => Http::response(['ret' => 401], 200),
    ]);

    $result = $this->service->check('alltick', 'bad-token');

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->toBe('AllTick responded with error code.');
});

test('checkAllTick returns error when API returns non-200 HTTP', function () {
    Http::fake([
        'quote.alltick.co/*' => Http::response('error', 500),
    ]);

    $result = $this->service->check('alltick', 'token');

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->toBe('AllTick did not respond with 200.');
});

// ── FreeCryptoAPI ────────────────────────────────────────────────────

test('checkFreeCryptoApi returns ok for valid key', function () {
    Http::fake([
        'api.freecryptoapi.com/*' => Http::response(['coins' => ['BTC']], 200),
    ]);

    $result = $this->service->check('freecryptoapi', 'valid-key');

    expect($result['ok'])->toBeTrue();
    expect($result['message'])->toBe('FreeCryptoAPI key is valid.');
});

test('checkFreeCryptoApi returns error for non-200 response', function () {
    Http::fake([
        'api.freecryptoapi.com/*' => Http::response('Unauthorized', 401),
    ]);

    $result = $this->service->check('freecryptoapi', 'bad-key');

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->toBe('FreeCryptoAPI did not respond with 200.');
});

test('checkFreeCryptoApi returns error for empty response', function () {
    Http::fake([
        'api.freecryptoapi.com/*' => Http::response([], 200),
    ]);

    $result = $this->service->check('freecryptoapi', 'key');

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->toBe('FreeCryptoAPI response is empty.');
});

test('checkFreeCryptoApi sends bearer token', function () {
    Http::fake([
        'api.freecryptoapi.com/*' => Http::response(['data' => true], 200),
    ]);

    $this->service->check('freecryptoapi', 'my-token-123');

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer my-token-123');
    });
});

// ── Bybit ────────────────────────────────────────────────────────────

test('checkBybit returns error when api_secret is missing', function () {
    $result = $this->service->check('bybit', 'api-key', null);

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->toBe('Bybit API secret is missing.');
});

test('checkBybit returns ok for valid credentials', function () {
    Http::fake([
        'api.bybit.com/*' => Http::response(['retCode' => 0], 200),
    ]);

    $result = $this->service->check('bybit', 'api-key', 'api-secret');

    expect($result['ok'])->toBeTrue();
    expect($result['message'])->toBe('Bybit API key & secret are valid.');
});

test('checkBybit returns error for invalid credentials', function () {
    Http::fake([
        'api.bybit.com/*' => Http::response(['retCode' => 10003], 200),
    ]);

    $result = $this->service->check('bybit', 'api-key', 'bad-secret');

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->toBe('Bybit reported API key error.');
});

test('checkBybit returns error when API returns non-200', function () {
    Http::fake([
        'api.bybit.com/*' => Http::response('error', 500),
    ]);

    $result = $this->service->check('bybit', 'api-key', 'api-secret');

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->toBe('Bybit did not respond with 200.');
});

test('checkBybit sends required HMAC headers', function () {
    Http::fake([
        'api.bybit.com/*' => Http::response(['retCode' => 0], 200),
    ]);

    $this->service->check('bybit', 'my-api-key', 'my-secret');

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-BAPI-API-KEY', 'my-api-key')
            && $request->hasHeader('X-BAPI-TIMESTAMP')
            && $request->hasHeader('X-BAPI-RECV-WINDOW', '5000')
            && $request->hasHeader('X-BAPI-SIGN');
    });
});

// ── OpenAI ───────────────────────────────────────────────────────────

test('checkOpenAI returns ok for valid key', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['data' => []], 200),
    ]);

    $result = $this->service->check('openai', 'sk-valid');

    expect($result['ok'])->toBeTrue();
    expect($result['message'])->toBe('OpenAI API key looks valid.');
});

test('checkOpenAI returns error for 401 response', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'invalid'], 401),
    ]);

    $result = $this->service->check('openai', 'sk-bad');

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->toContain('401');
});

test('checkOpenAI returns generic error for non-401 failure', function () {
    Http::fake([
        'api.openai.com/*' => Http::response('error', 500),
    ]);

    $result = $this->service->check('openai', 'sk-key');

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->toBe('OpenAI health check failed.');
    expect($result['details']['status'])->toBe(500);
});

// ── Default / Unknown ────────────────────────────────────────────────

test('check returns error for unknown provider', function () {
    $result = $this->service->check('unknown-provider', 'key');

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->toBe('Health check not implemented for this provider.');
});

// ── Exception handling ───────────────────────────────────────────────

test('check catches throwable and returns error', function () {
    Http::fake([
        'quote.alltick.co/*' => fn () => throw new \RuntimeException('Connection timeout'),
    ]);

    $result = $this->service->check('alltick', 'token');

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->toContain('Connection timeout');
});
