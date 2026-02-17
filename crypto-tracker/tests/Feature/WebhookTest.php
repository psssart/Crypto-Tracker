<?php

use App\Jobs\ProcessCryptoWebhook;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Queue;

test('webhook endpoint accepts payload and returns 202', function () {
    Queue::fake();

    $this->postJson(route('webhooks.crypto'), [
        'event' => 'transfer',
        'chain' => 'ethereum',
        'data' => ['tx_hash' => '0xabc'],
    ])->assertStatus(202)
      ->assertJson(['status' => 'accepted']);
});

test('webhook endpoint logs payload to database', function () {
    Queue::fake();

    $payload = ['event' => 'swap', 'chain' => 'polygon'];

    $this->postJson(route('webhooks.crypto'), $payload);

    $this->assertDatabaseCount('webhook_logs', 1);

    $log = WebhookLog::first();
    expect($log->payload['event'])->toBe('swap');
    expect($log->payload['chain'])->toBe('polygon');
    expect($log->processed_at)->toBeNull();
});

test('webhook endpoint dispatches ProcessCryptoWebhook job', function () {
    Queue::fake();

    $this->postJson(route('webhooks.crypto'), ['event' => 'mint']);

    Queue::assertPushed(ProcessCryptoWebhook::class, function ($job) {
        return $job->webhookLog->id === WebhookLog::first()->id;
    });
});

test('webhook endpoint is exempt from CSRF verification', function () {
    Queue::fake();

    // Regular POST (not JSON) should also work without CSRF token
    $this->post(route('webhooks.crypto'), ['event' => 'test'])
        ->assertStatus(202);
});
