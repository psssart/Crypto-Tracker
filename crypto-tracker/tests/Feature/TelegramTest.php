<?php

use App\Models\TelegramChat;
use App\Models\TelegramMessage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

// ── Models & Relations ──────────────────────────────────────────────

test('user has telegramChat relation', function () {
    $user = User::factory()->create();

    expect($user->telegramChat)->toBeNull();

    $chat = TelegramChat::create([
        'user_id' => $user->id,
        'chat_id' => '123456',
        'username' => 'testuser',
    ]);

    expect($user->refresh()->telegramChat->id)->toBe($chat->id);
});

test('telegram chat has messages relation', function () {
    $chat = TelegramChat::create([
        'chat_id' => '123456',
        'username' => 'testuser',
    ]);

    TelegramMessage::create([
        'telegram_chat_id' => $chat->id,
        'direction' => 'in',
        'text' => 'hello',
    ]);

    expect($chat->messages)->toHaveCount(1);
    expect($chat->messages->first()->text)->toBe('hello');
});

test('telegram message casts raw_payload to array', function () {
    $chat = TelegramChat::create(['chat_id' => '999']);

    $msg = TelegramMessage::create([
        'telegram_chat_id' => $chat->id,
        'direction' => 'out',
        'text' => 'test',
        'raw_payload' => ['key' => 'value'],
    ]);

    $msg->refresh();
    expect($msg->raw_payload)->toBeArray();
    expect($msg->raw_payload['key'])->toBe('value');
});

test('deleting telegram chat cascades to messages', function () {
    $chat = TelegramChat::create(['chat_id' => '111']);

    TelegramMessage::create([
        'telegram_chat_id' => $chat->id,
        'direction' => 'in',
        'text' => 'bye',
    ]);

    $chat->delete();

    expect(TelegramMessage::count())->toBe(0);
});

test('deleting user nullifies telegram chat user_id', function () {
    $user = User::factory()->create();

    $chat = TelegramChat::create([
        'user_id' => $user->id,
        'chat_id' => '222',
    ]);

    $user->delete();

    expect($chat->refresh()->user_id)->toBeNull();
});

// ── routeNotificationForTelegram ────────────────────────────────────

test('routeNotificationForTelegram returns chat_id when linked', function () {
    $user = User::factory()->create();

    TelegramChat::create([
        'user_id' => $user->id,
        'chat_id' => '789',
    ]);

    expect($user->routeNotificationForTelegram())->toBe('789');
});

test('routeNotificationForTelegram returns null when not linked', function () {
    $user = User::factory()->create();

    expect($user->routeNotificationForTelegram())->toBeNull();
});

// ── Profile: Telegram Link ──────────────────────────────────────────

test('profile page shows telegram linked status', function () {
    $user = User::factory()->create();

    TelegramChat::create([
        'user_id' => $user->id,
        'chat_id' => '456',
        'username' => 'linked_user',
    ]);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk();
    $page = $response->original->getData()['page'];
    expect($page['props']['telegramLinked'])->toBeTrue();
    expect($page['props']['telegramUsername'])->toBe('linked_user');
});

test('profile page shows telegram unlinked status', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk();
    $page = $response->original->getData()['page'];
    expect($page['props']['telegramLinked'])->toBeFalse();
    expect($page['props']['telegramUsername'])->toBeNull();
});

test('telegram link generates token and returns url', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('profile.telegram-link'));

    $response->assertOk();
    $data = $response->json();

    expect($data['url'])->toContain('t.me/');
    expect($data['url'])->toContain('?start=');

    // Extract token and verify cache
    preg_match('/start=(.+)$/', $data['url'], $matches);
    $token = $matches[1];
    expect(Cache::get("telegram_link:{$token}"))->toBe($user->id);
});

test('telegram link requires authentication', function () {
    $this->post(route('profile.telegram-link'))
        ->assertRedirect(route('login'));
});

test('telegram unlink removes chat record', function () {
    $user = User::factory()->create();

    TelegramChat::create([
        'user_id' => $user->id,
        'chat_id' => '333',
    ]);

    $this->actingAs($user)
        ->post(route('profile.telegram-unlink'))
        ->assertRedirect('/profile');

    expect($user->refresh()->telegramChat)->toBeNull();
    expect(TelegramChat::count())->toBe(0);
});

test('telegram unlink requires authentication', function () {
    $this->post(route('profile.telegram-unlink'))
        ->assertRedirect(route('login'));
});
