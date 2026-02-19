<?php

use App\Models\Network;
use App\Models\Wallet;
use App\Services\WebhookAddressService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Http::fake([
        'dashboard.alchemy.com/*' => Http::response([], 200),
        'api.moralis-streams.com/*' => Http::response([], 200),
    ]);
});

// ── Alchemy routing ─────────────────────────────────────────────────

test('addAddress calls Alchemy for ethereum network', function () {
    config([
        'services.alchemy.auth_token' => 'test-token',
        'services.alchemy.ethereum' => 'wh_ethereum',
    ]);

    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create(['address' => '0xabc']);

    app(WebhookAddressService::class)->addAddress($wallet);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://dashboard.alchemy.com/api/update-webhook-addresses'
            && $request->method() === 'PATCH'
            && $request->hasHeader('X-Alchemy-Token', 'test-token')
            && $request['webhook_id'] === 'wh_ethereum'
            && $request['addresses_to_add'] === ['0xabc']
            && $request['addresses_to_remove'] === [];
    });
});

test('removeAddress calls Alchemy for polygon network', function () {
    config([
        'services.alchemy.auth_token' => 'test-token',
        'services.alchemy.polygon' => 'wh_polygon',
    ]);

    $network = Network::factory()->polygon()->create();
    $wallet = Wallet::factory()->for($network)->create(['address' => '0xdef']);

    app(WebhookAddressService::class)->removeAddress($wallet);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://dashboard.alchemy.com/api/update-webhook-addresses'
            && $request->method() === 'PATCH'
            && $request['addresses_to_add'] === []
            && $request['addresses_to_remove'] === ['0xdef'];
    });
});

// ── Moralis routing ─────────────────────────────────────────────────

test('addAddress calls Moralis for bsc network', function () {
    config([
        'services.moralis.api_key' => 'test-moralis-key',
        'services.moralis.stream_id' => 'stream-123',
    ]);

    $network = Network::factory()->bsc()->create();
    $wallet = Wallet::factory()->for($network)->create(['address' => '0xbsc']);

    app(WebhookAddressService::class)->addAddress($wallet);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.moralis-streams.com/streams/evm/stream-123/address'
            && $request->method() === 'POST'
            && $request->hasHeader('X-API-Key', 'test-moralis-key')
            && $request['address'] === '0xbsc';
    });
});

test('removeAddress calls Moralis DELETE for bsc network', function () {
    config([
        'services.moralis.api_key' => 'test-moralis-key',
        'services.moralis.stream_id' => 'stream-123',
    ]);

    $network = Network::factory()->bsc()->create();
    $wallet = Wallet::factory()->for($network)->create(['address' => '0xbsc']);

    app(WebhookAddressService::class)->removeAddress($wallet);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'api.moralis-streams.com/streams/evm/stream-123/address')
            && $request->method() === 'DELETE';
    });
});

// ── Non-EVM warning ─────────────────────────────────────────────────

test('addAddress logs warning for non-EVM network', function () {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'non-EVM'));

    $network = Network::factory()->create(['slug' => 'bitcoin', 'name' => 'Bitcoin']);
    $wallet = Wallet::factory()->for($network)->create();

    app(WebhookAddressService::class)->addAddress($wallet);

    Http::assertNothingSent();
});

// ── Missing config graceful no-op ───────────────────────────────────

test('addAddress skips when Alchemy config is missing', function () {
    config([
        'services.alchemy.auth_token' => null,
        'services.alchemy.ethereum' => null,
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'Alchemy config missing'));

    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();

    app(WebhookAddressService::class)->addAddress($wallet);

    Http::assertNothingSent();
});

test('addAddress skips when Moralis config is missing', function () {
    config([
        'services.moralis.api_key' => null,
        'services.moralis.stream_id' => null,
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg) => str_contains($msg, 'Moralis config missing'));

    $network = Network::factory()->bsc()->create();
    $wallet = Wallet::factory()->for($network)->create();

    app(WebhookAddressService::class)->addAddress($wallet);

    Http::assertNothingSent();
});
