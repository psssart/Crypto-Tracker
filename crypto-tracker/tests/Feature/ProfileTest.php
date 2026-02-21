<?php

use App\Jobs\UpdateWebhookAddress;
use App\Models\Network;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Queue;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});

// ── Webhook cleanup on account deletion ─────────────────────────────

test('account deletion dispatches webhook remove for orphaned wallets', function () {
    Queue::fake();
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $user->wallets()->attach($wallet->id);

    $this
        ->actingAs($user)
        ->delete('/profile', ['password' => 'password']);

    Queue::assertPushed(UpdateWebhookAddress::class, function ($job) use ($wallet) {
        return $job->action === 'remove' && $job->wallet->id === $wallet->id;
    });
});

test('account deletion does not dispatch webhook remove for wallets tracked by others', function () {
    Queue::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $user->wallets()->attach($wallet->id);
    $other->wallets()->attach($wallet->id);

    $this
        ->actingAs($user)
        ->delete('/profile', ['password' => 'password']);

    Queue::assertNotPushed(UpdateWebhookAddress::class);
});
