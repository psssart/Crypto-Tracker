<?php

use App\Jobs\ProcessCryptoWebhook;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Queue;
use kornrunner\Keccak;

test('moralis webhook rejects request without signature', function () {
    Queue::fake();

    $this->postJson(route('webhooks.moralis'), ['confirmed' => true])
        ->assertStatus(401)
        ->assertJson(['error' => 'Invalid signature']);

    Queue::assertNothingPushed();
});

test('moralis webhook accepts payload with valid signature and returns 202', function () {
    Queue::fake();
    config(['services.moralis.api_key' => 'test-moralis-secret']);

    $payload = ['confirmed' => true, 'chainId' => '0x1', 'txs' => []];
    $body = json_encode($payload);
    $signature = Keccak::hash($body . 'test-moralis-secret', 256);

    $this->call('POST', route('webhooks.moralis'), [], [], [], [
        'HTTP_X_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)
        ->assertStatus(202)
        ->assertJson(['status' => 'accepted']);
});

test('moralis webhook logs payload with source', function () {
    Queue::fake();
    config(['services.moralis.api_key' => 'test-moralis-secret']);

    $payload = ['confirmed' => true, 'chainId' => '0x1', 'txs' => []];
    $body = json_encode($payload);
    $signature = Keccak::hash($body . 'test-moralis-secret', 256);

    $this->call('POST', route('webhooks.moralis'), [], [], [], [
        'HTTP_X_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $this->assertDatabaseCount('webhook_logs', 1);

    $log = WebhookLog::first();
    expect($log->source)->toBe('moralis');
    expect($log->payload['confirmed'])->toBeTrue();
    expect($log->processed_at)->toBeNull();
});

test('moralis webhook dispatches ProcessCryptoWebhook job', function () {
    Queue::fake();
    config(['services.moralis.api_key' => 'test-moralis-secret']);

    $payload = ['confirmed' => true, 'chainId' => '0x1', 'txs' => []];
    $body = json_encode($payload);
    $signature = Keccak::hash($body . 'test-moralis-secret', 256);

    $this->call('POST', route('webhooks.moralis'), [], [], [], [
        'HTTP_X_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    Queue::assertPushed(ProcessCryptoWebhook::class, function ($job) {
        return $job->webhookLog->id === WebhookLog::first()->id;
    });
});

test('alchemy webhook rejects request without signature', function () {
    Queue::fake();

    $this->postJson(route('webhooks.alchemy'), ['event' => []])
        ->assertStatus(401)
        ->assertJson(['error' => 'Invalid signature']);

    Queue::assertNothingPushed();
});

test('alchemy webhook accepts payload with valid signature and returns 202', function () {
    Queue::fake();
    config(['services.alchemy.auth_token' => 'test-alchemy-secret']);

    $payload = ['event' => ['network' => 'ETH_MAINNET', 'activity' => []]];
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, 'test-alchemy-secret');

    $this->call('POST', route('webhooks.alchemy'), [], [], [], [
        'HTTP_X_ALCHEMY_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)
        ->assertStatus(202)
        ->assertJson(['status' => 'accepted']);
});

test('alchemy webhook logs payload with source', function () {
    Queue::fake();
    config(['services.alchemy.auth_token' => 'test-alchemy-secret']);

    $payload = ['event' => ['network' => 'ETH_MAINNET', 'activity' => []]];
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, 'test-alchemy-secret');

    $this->call('POST', route('webhooks.alchemy'), [], [], [], [
        'HTTP_X_ALCHEMY_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $this->assertDatabaseCount('webhook_logs', 1);

    $log = WebhookLog::first();
    expect($log->source)->toBe('alchemy');
    expect($log->processed_at)->toBeNull();
});
