<?php

use App\Models\User;
use App\Models\UserIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user integration belongs to user', function () {
    $user = User::factory()->create();
    $integration = UserIntegration::factory()->for($user)->alltick()->create();

    expect($integration->user->id)->toBe($user->id);
});

test('user has many integrations', function () {
    $user = User::factory()->create();

    UserIntegration::factory()->for($user)->alltick()->create();
    UserIntegration::factory()->for($user)->openai()->create();

    expect($user->integrations)->toHaveCount(2);
});

test('api_key is encrypted at rest', function () {
    $user = User::factory()->create();
    $integration = UserIntegration::factory()->for($user)->alltick()->create([
        'api_key' => 'plain-text-key',
    ]);

    // Fetch raw value from DB, bypassing Eloquent casts
    $raw = \DB::table('user_integrations')
        ->where('id', $integration->id)
        ->value('api_key');

    expect($raw)->not->toBe('plain-text-key');

    // But through Eloquent it should be decrypted
    $integration->refresh();
    expect($integration->api_key)->toBe('plain-text-key');
});

test('settings is encrypted at rest', function () {
    $user = User::factory()->create();
    $integration = UserIntegration::factory()->for($user)->bybit()->create([
        'settings' => ['api_secret' => 'my-secret'],
    ]);

    $raw = \DB::table('user_integrations')
        ->where('id', $integration->id)
        ->value('settings');

    expect($raw)->not->toContain('my-secret');

    $integration->refresh();
    expect($integration->settings)->toBe(['api_secret' => 'my-secret']);
});

test('integrations are deleted when user is deleted', function () {
    $user = User::factory()->create();
    UserIntegration::factory()->for($user)->alltick()->create();
    UserIntegration::factory()->for($user)->openai()->create();

    expect(UserIntegration::where('user_id', $user->id)->count())->toBe(2);

    $user->delete();

    expect(UserIntegration::where('user_id', $user->id)->count())->toBe(0);
});

test('last_used_at and revoked_at are cast to datetime', function () {
    $user = User::factory()->create();
    $integration = UserIntegration::factory()->for($user)->alltick()->create([
        'last_used_at' => '2025-06-01 12:00:00',
        'revoked_at' => '2025-06-15 12:00:00',
    ]);

    $integration->refresh();

    expect($integration->last_used_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($integration->revoked_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('unique constraint prevents duplicate user-provider pairs', function () {
    $user = User::factory()->create();
    UserIntegration::factory()->for($user)->alltick()->create();

    UserIntegration::factory()->for($user)->alltick()->create();
})->throws(\Illuminate\Database\UniqueConstraintViolationException::class);
