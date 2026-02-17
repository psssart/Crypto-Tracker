<?php

use App\Models\Network;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

test('wallet belongs to network', function () {
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();

    expect($wallet->network->id)->toBe($network->id);
    expect($wallet->network->slug)->toBe('ethereum');
});

test('wallet has many transactions', function () {
    $wallet = Wallet::factory()->create();
    Transaction::factory()->for($wallet)->count(3)->create();

    expect($wallet->transactions)->toHaveCount(3);
});

test('wallet belongs to many users via pivot', function () {
    $wallet = Wallet::factory()->create();
    $user = User::factory()->create();

    $user->wallets()->attach($wallet->id, [
        'custom_label' => 'Test',
        'is_notified' => true,
        'notify_threshold_usd' => '500.000000000000000000',
    ]);

    $pivot = $wallet->users->first()->pivot;
    expect($pivot->custom_label)->toBe('Test');
    expect((bool) $pivot->is_notified)->toBeTrue();
});

test('wallet balance_usd is cast as decimal', function () {
    $network = Network::factory()->create();
    $wallet = Wallet::factory()->for($network)->create([
        'balance_usd' => '12345.50',
    ]);

    $wallet->refresh();
    expect($wallet->balance_usd)->toContain('12345.5');
    expect($wallet->getCasts()['balance_usd'])->toBe('decimal:18');
});
