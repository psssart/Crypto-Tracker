<?php

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\IntegrationHealthService;

// ── Show ─────────────────────────────────────────────────────────────

test('chart page requires authentication', function () {
    $this->get(route('chart'))
        ->assertRedirect(route('login'));
});

test('chart page requires verified email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('chart'))
        ->assertRedirect(route('verification.notice'));
});

test('chart page renders for verified user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('chart'))
        ->assertOk();
});

test('chart page includes source auth for configured integrations', function () {
    $user = User::factory()->create();

    UserIntegration::factory()->for($user)->alltick()->create([
        'api_key' => 'at-token-123',
    ]);
    UserIntegration::factory()->for($user)->freecryptoapi()->create([
        'api_key' => 'fca-key-456',
    ]);

    $response = $this->actingAs($user)->get(route('chart'));
    $response->assertOk();

    $page = $response->original->getData()['page'];
    $sourceAuth = $page['props']['sourceAuth'];

    expect($sourceAuth['alltick'])->toBe(['token' => 'at-token-123']);
    expect($sourceAuth['freecryptoapi'])->toBe(['apiKey' => 'fca-key-456']);
});

test('chart page includes bybit source auth with api secret', function () {
    $user = User::factory()->create();

    UserIntegration::factory()->for($user)->create([
        'provider' => 'bybit',
        'api_key' => 'bybit-key',
        'settings' => ['api_secret' => 'bybit-secret'],
    ]);

    $response = $this->actingAs($user)->get(route('chart'));
    $page = $response->original->getData()['page'];
    $sourceAuth = $page['props']['sourceAuth'];

    expect($sourceAuth['bybit'])->toBe([
        'apiKey' => 'bybit-key',
        'apiSecret' => 'bybit-secret',
    ]);
});

test('chart page omits source auth for providers without integration', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('chart'));
    $page = $response->original->getData()['page'];
    $sourceAuth = $page['props']['sourceAuth'];

    expect($sourceAuth)->toBeEmpty();
});

test('chart page includes integration backed source ids', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('chart'));
    $page = $response->original->getData()['page'];
    $sourceIds = $page['props']['integrationBackedSourceIds'];

    expect($sourceIds)->toContain('alltick');
    expect($sourceIds)->toContain('freecryptoapi');
    expect($sourceIds)->toContain('bybit');
});

// ── Check Source ─────────────────────────────────────────────────────

test('checkSource returns error for unknown ws_source_id', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('chart.checkSource'), [
            'ws_source_id' => 'nonexistent',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'message' => 'No integration is associated with this data source.',
        ]);
});

test('checkSource returns error when user has no integration for source', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('chart.checkSource'), [
            'ws_source_id' => 'alltick',
        ]);

    $response->assertOk()
        ->assertJson([
            'ok' => false,
            'message' => 'AllTick integration is not configured.',
        ]);
});

test('checkSource calls health check when integration exists', function () {
    $user = User::factory()->create();
    UserIntegration::factory()->for($user)->alltick()->create([
        'api_key' => 'valid-token',
    ]);

    $this->mock(IntegrationHealthService::class)
        ->shouldReceive('check')
        ->with('alltick', 'valid-token', null)
        ->once()
        ->andReturn(['ok' => true, 'message' => 'AllTick token is valid.']);

    $response = $this->actingAs($user)
        ->postJson(route('chart.checkSource'), [
            'ws_source_id' => 'alltick',
        ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'provider' => 'alltick',
        ]);
});

test('checkSource passes api_secret for bybit', function () {
    $user = User::factory()->create();
    UserIntegration::factory()->for($user)->create([
        'provider' => 'bybit',
        'api_key' => 'bybit-key',
        'settings' => ['api_secret' => 'bybit-secret'],
    ]);

    $this->mock(IntegrationHealthService::class)
        ->shouldReceive('check')
        ->with('bybit', 'bybit-key', 'bybit-secret')
        ->once()
        ->andReturn(['ok' => true, 'message' => 'Bybit API key & secret are valid.']);

    $response = $this->actingAs($user)
        ->postJson(route('chart.checkSource'), [
            'ws_source_id' => 'bybit',
        ]);

    $response->assertOk()
        ->assertJson(['ok' => true, 'provider' => 'bybit']);
});

test('checkSource validates ws_source_id is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('chart.checkSource'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ws_source_id');
});
