<?php

use App\Models\User;
use App\Models\UserIntegration;
use App\Services\OpenAIService;

test('openai respond requires authentication', function () {
    $this->postJson('/openai/respond', [
        'input' => 'Hello',
    ])->assertUnauthorized();
});

test('openai respond requires verified email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post('/openai/respond', [
            'input' => 'Hello',
        ])
        ->assertRedirect(route('verification.notice'));
});

test('openai respond returns error when no integration configured', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/openai/respond', [
            'input' => 'Hello',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'message' => 'No OpenAI integration configured for this user.',
        ]);
});

test('openai respond returns error when integration has no api_key', function () {
    $user = User::factory()->create();
    UserIntegration::factory()->for($user)->openai()->create([
        'api_key' => null,
    ]);

    $response = $this->actingAs($user)
        ->postJson('/openai/respond', [
            'input' => 'Hello',
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'message' => 'No OpenAI integration configured for this user.',
        ]);
});

test('openai respond returns successful response', function () {
    $user = User::factory()->create();
    $integration = UserIntegration::factory()->for($user)->openai()->create([
        'api_key' => 'sk-test-key',
    ]);

    $this->mock(OpenAIService::class)
        ->shouldReceive('responses')
        ->with('sk-test-key', 'gpt-4o-mini', 'Hello')
        ->once()
        ->andReturn('Hi there!');

    $response = $this->actingAs($user)
        ->postJson('/openai/respond', [
            'input' => 'Hello',
        ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'text' => 'Hi there!',
            'model' => 'gpt-4o-mini',
        ]);
});

test('openai respond uses custom model when provided', function () {
    $user = User::factory()->create();
    UserIntegration::factory()->for($user)->openai()->create([
        'api_key' => 'sk-test-key',
    ]);

    $this->mock(OpenAIService::class)
        ->shouldReceive('responses')
        ->with('sk-test-key', 'gpt-4o', 'Hello')
        ->once()
        ->andReturn('Response from gpt-4o');

    $response = $this->actingAs($user)
        ->postJson('/openai/respond', [
            'input' => 'Hello',
            'model' => 'gpt-4o',
        ]);

    $response->assertOk()
        ->assertJson([
            'model' => 'gpt-4o',
        ]);
});

test('openai respond updates last_used_at timestamp', function () {
    $user = User::factory()->create();
    $integration = UserIntegration::factory()->for($user)->openai()->create([
        'api_key' => 'sk-test-key',
        'last_used_at' => null,
    ]);

    $this->mock(OpenAIService::class)
        ->shouldReceive('responses')
        ->andReturn('response');

    $this->actingAs($user)
        ->postJson('/openai/respond', [
            'input' => 'Hello',
        ]);

    $integration->refresh();
    expect($integration->last_used_at)->not->toBeNull();
});

test('openai respond validates input is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/openai/respond', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('input');
});

test('openai respond validates input max length', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/openai/respond', [
            'input' => str_repeat('a', 8001),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('input');
});
