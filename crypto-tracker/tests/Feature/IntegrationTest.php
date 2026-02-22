<?php

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\IntegrationHealthService;
use Illuminate\Support\Facades\Http;

// ── Index ────────────────────────────────────────────────────────────

test('integrations page requires authentication', function () {
    $this->get(route('integrations.index'))
        ->assertRedirect(route('login'));
});

test('integrations page requires verified email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('integrations.index'))
        ->assertRedirect(route('verification.notice'));
});

test('integrations page renders for verified user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('integrations.index'))
        ->assertOk();
});

test('integrations index returns user integrations with masked keys', function () {
    $user = User::factory()->create();
    UserIntegration::factory()->for($user)->alltick()->create([
        'api_key' => 'my-secret-key-12345',
    ]);

    $response = $this->actingAs($user)
        ->get(route('integrations.index'));

    $response->assertOk();

    $page = $response->original->getData()['page'];
    $integrations = $page['props']['integrations'];

    expect($integrations)->toHaveCount(1);
    expect($integrations[0]['masked_key'])->toBe('****2345');
    expect($integrations[0]['has_api_key'])->toBeTrue();
    expect($integrations[0]['provider'])->toBe('alltick');
});

test('integrations index does not show other users integrations', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    UserIntegration::factory()->for($other)->alltick()->create();

    $response = $this->actingAs($user)
        ->get(route('integrations.index'));

    $page = $response->original->getData()['page'];
    $integrations = $page['props']['integrations'];

    expect($integrations)->toHaveCount(0);
});

// ── Store ────────────────────────────────────────────────────────────

test('store creates a new integration', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('integrations.store'), [
            'provider' => 'alltick',
            'api_key' => 'test-key-123',
        ]);

    $response->assertRedirect(route('integrations.index'));
    $response->assertSessionHas('status', 'Integration successfully saved');

    $this->assertDatabaseHas('user_integrations', [
        'user_id' => $user->id,
        'provider' => 'alltick',
    ]);
});

test('store upserts when same provider exists', function () {
    $user = User::factory()->create();
    UserIntegration::factory()->for($user)->alltick()->create([
        'api_key' => 'old-key',
    ]);

    $this->actingAs($user)
        ->post(route('integrations.store'), [
            'provider' => 'alltick',
            'api_key' => 'new-key',
        ]);

    $integrations = $user->integrations()->where('provider', 'alltick')->get();
    expect($integrations)->toHaveCount(1);
    expect($integrations->first()->api_key)->toBe('new-key');
});

test('store saves bybit api_secret in settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('integrations.store'), [
            'provider' => 'bybit',
            'api_key' => 'bybit-key',
            'api_secret' => 'bybit-secret',
        ]);

    $integration = $user->integrations()->where('provider', 'bybit')->first();
    expect($integration->settings)->toBe(['api_secret' => 'bybit-secret']);
});

test('store does not save api_secret for non-bybit providers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('integrations.store'), [
            'provider' => 'alltick',
            'api_key' => 'test-key',
            'api_secret' => 'some-secret',
        ]);

    $integration = $user->integrations()->where('provider', 'alltick')->first();
    expect($integration->settings)->toBeNull();
});

test('store validates provider must be a known provider', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('integrations.store'), [
            'provider' => 'unknown-provider',
            'api_key' => 'test-key',
        ])
        ->assertSessionHasErrors('provider');
});

test('store validates api_key is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('integrations.store'), [
            'provider' => 'alltick',
        ])
        ->assertSessionHasErrors('api_key');
});

test('store requires authentication', function () {
    $this->post(route('integrations.store'), [
        'provider' => 'alltick',
        'api_key' => 'test',
    ])->assertRedirect(route('login'));
});

// ── Update ───────────────────────────────────────────────────────────

test('update modifies an existing integration', function () {
    $user = User::factory()->create();
    $integration = UserIntegration::factory()->for($user)->alltick()->create([
        'api_key' => 'old-key',
    ]);

    $response = $this->actingAs($user)
        ->patch(route('integrations.update', $integration), [
            'provider' => 'alltick',
            'api_key' => 'updated-key',
        ]);

    $response->assertRedirect(route('integrations.index'));
    $response->assertSessionHas('status', 'Integration updated');

    $integration->refresh();
    expect($integration->api_key)->toBe('updated-key');
});

test('update preserves api_key when not provided', function () {
    $user = User::factory()->create();
    $integration = UserIntegration::factory()->for($user)->alltick()->create([
        'api_key' => 'original-key',
    ]);

    $this->actingAs($user)
        ->patch(route('integrations.update', $integration), [
            'provider' => 'alltick',
        ]);

    $integration->refresh();
    expect($integration->api_key)->toBe('original-key');
});

test('update rejects other users integration', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $integration = UserIntegration::factory()->for($other)->alltick()->create();

    $this->actingAs($user)
        ->patch(route('integrations.update', $integration), [
            'provider' => 'alltick',
            'api_key' => 'hacked',
        ])
        ->assertForbidden();
});

test('update saves bybit api_secret in settings', function () {
    $user = User::factory()->create();
    $integration = UserIntegration::factory()->for($user)->bybit()->create();

    $this->actingAs($user)
        ->patch(route('integrations.update', $integration), [
            'provider' => 'bybit',
            'api_secret' => 'new-secret',
        ]);

    $integration->refresh();
    expect($integration->settings['api_secret'])->toBe('new-secret');
});

// ── Destroy ──────────────────────────────────────────────────────────

test('destroy deletes an integration', function () {
    $user = User::factory()->create();
    $integration = UserIntegration::factory()->for($user)->alltick()->create();

    $response = $this->actingAs($user)
        ->delete(route('integrations.destroy', $integration));

    $response->assertRedirect(route('integrations.index'));
    $response->assertSessionHas('status', 'Integration deleted');

    $this->assertDatabaseMissing('user_integrations', ['id' => $integration->id]);
});

test('destroy rejects other users integration', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $integration = UserIntegration::factory()->for($other)->alltick()->create();

    $this->actingAs($user)
        ->delete(route('integrations.destroy', $integration))
        ->assertForbidden();

    $this->assertDatabaseHas('user_integrations', ['id' => $integration->id]);
});

// ── Check (health check) ────────────────────────────────────────────

test('check returns success for valid credentials', function () {
    $user = User::factory()->create();

    $this->mock(IntegrationHealthService::class)
        ->shouldReceive('check')
        ->with('alltick', 'test-key', null)
        ->once()
        ->andReturn(['ok' => true, 'message' => 'AllTick token is valid.']);

    $response = $this->actingAs($user)
        ->postJson(route('integrations.check'), [
            'provider' => 'alltick',
            'api_key' => 'test-key',
        ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'message' => 'AllTick token is valid.',
        ]);
});

test('check returns error for unknown provider', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('integrations.check'), [
            'provider' => 'nonexistent',
            'api_key' => 'test-key',
        ]);

    $response->assertStatus(422)
        ->assertJson(['ok' => false, 'message' => 'Unknown integration provider.']);
});

test('check returns error when health check is disabled', function () {
    $user = User::factory()->create();

    // Temporarily override config to disable health check for alltick
    config(['integrations.providers.alltick.health_check.enabled' => false]);

    $response = $this->actingAs($user)
        ->postJson(route('integrations.check'), [
            'provider' => 'alltick',
            'api_key' => 'test-key',
        ]);

    $response->assertStatus(422)
        ->assertJson(['ok' => false, 'message' => 'Health check is not enabled for this provider.']);
});

test('check passes api_secret for bybit', function () {
    $user = User::factory()->create();

    $this->mock(IntegrationHealthService::class)
        ->shouldReceive('check')
        ->with('bybit', 'bybit-key', 'bybit-secret')
        ->once()
        ->andReturn(['ok' => true, 'message' => 'Bybit API key & secret are valid.']);

    $response = $this->actingAs($user)
        ->postJson(route('integrations.check'), [
            'provider' => 'bybit',
            'api_key' => 'bybit-key',
            'api_secret' => 'bybit-secret',
        ]);

    $response->assertOk()
        ->assertJson(['ok' => true]);
});

test('check validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('integrations.check'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['provider', 'api_key']);
});
