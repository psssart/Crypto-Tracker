<?php

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\CryptoProviderService;

test('resolveApiKey returns user integration key when available', function () {
    $user = User::factory()->create();
    UserIntegration::factory()->for($user)->create([
        'provider' => 'coingecko',
        'api_key' => 'user-cg-key',
    ]);

    $service = new CryptoProviderService();
    $key = $service->resolveApiKey('coingecko', $user);

    expect($key)->toBe('user-cg-key');
});

test('resolveApiKey falls back to env config when no user key', function () {
    $user = User::factory()->create();
    config(['services.coingecko.key' => 'env-cg-key']);

    $service = new CryptoProviderService();
    $key = $service->resolveApiKey('coingecko', $user);

    expect($key)->toBe('env-cg-key');
});

test('resolveApiKey uses api_key config path for moralis', function () {
    config(['services.moralis.api_key' => 'moralis-env-key']);

    $service = new CryptoProviderService();
    $key = $service->resolveApiKey('moralis');

    expect($key)->toBe('moralis-env-key');
});

test('resolveApiKey skips revoked user integrations', function () {
    $user = User::factory()->create();
    UserIntegration::factory()->for($user)->create([
        'provider' => 'coingecko',
        'api_key' => 'revoked-key',
        'revoked_at' => now(),
    ]);

    config(['services.coingecko.key' => 'env-fallback']);

    $service = new CryptoProviderService();
    $key = $service->resolveApiKey('coingecko', $user);

    expect($key)->toBe('env-fallback');
});
