<?php

use App\Jobs\SyncWalletHistory;
use App\Models\Network;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Queue;

// ── Auth Guards ─────────────────────────────────────────────────────

test('watchlist page requires authentication', function () {
    $this->get(route('watchlist.index'))
        ->assertRedirect(route('login'));
});

test('watchlist page requires verified email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('watchlist.index'))
        ->assertRedirect(route('verification.notice'));
});

test('watchlist page renders for verified user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('watchlist.index'))
        ->assertOk();
});

// ── Index ───────────────────────────────────────────────────────────

test('watchlist index returns user wallets with networks', function () {
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();

    $user->wallets()->attach($wallet->id, ['custom_label' => 'My ETH']);

    $response = $this->actingAs($user)
        ->get(route('watchlist.index'));

    $response->assertOk();

    $page = $response->original->getData()['page'];
    $wallets = $page['props']['wallets'];

    expect($wallets)->toHaveCount(1);
    expect($wallets[0]['address'])->toBe($wallet->address);
    expect($wallets[0]['network']['slug'])->toBe('ethereum');
    expect($wallets[0]['pivot']['custom_label'])->toBe('My ETH');
});

test('watchlist index does not show other users wallets', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $other->wallets()->attach($wallet->id);

    $response = $this->actingAs($user)
        ->get(route('watchlist.index'));

    $page = $response->original->getData()['page'];
    $wallets = $page['props']['wallets'];

    expect($wallets)->toHaveCount(0);
});

test('watchlist index returns active networks', function () {
    $user = User::factory()->create();
    Network::factory()->ethereum()->create();
    Network::factory()->create(['is_active' => false]);

    $response = $this->actingAs($user)
        ->get(route('watchlist.index'));

    $page = $response->original->getData()['page'];
    $networks = $page['props']['networks'];

    expect($networks)->toHaveCount(1);
});

// ── Store ───────────────────────────────────────────────────────────

test('store creates wallet and attaches to user', function () {
    Queue::fake();
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();

    $this->actingAs($user)
        ->post(route('watchlist.store'), [
            'network_id' => $network->id,
            'address' => '0xABC123def456',
            'custom_label' => 'Test Wallet',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('wallets', [
        'network_id' => $network->id,
        'address' => '0xabc123def456', // lowercased
    ]);

    expect($user->wallets)->toHaveCount(1);
    expect($user->wallets->first()->pivot->custom_label)->toBe('Test Wallet');
});

test('store dispatches SyncWalletHistory job', function () {
    Queue::fake();
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();

    $this->actingAs($user)
        ->post(route('watchlist.store'), [
            'network_id' => $network->id,
            'address' => '0xabc123',
        ]);

    Queue::assertPushed(SyncWalletHistory::class, function ($job) use ($user) {
        return $job->userId === $user->id;
    });
});

test('store reuses existing wallet for same network and address', function () {
    Queue::fake();
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create(['address' => '0xabc']);

    $this->actingAs($user)
        ->post(route('watchlist.store'), [
            'network_id' => $network->id,
            'address' => '0xABC', // different case
        ]);

    expect(Wallet::count())->toBe(1);
    expect($user->wallets->first()->id)->toBe($wallet->id);
});

test('store prevents duplicate wallet attachment', function () {
    Queue::fake();
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();

    $this->actingAs($user)
        ->post(route('watchlist.store'), [
            'network_id' => $network->id,
            'address' => '0xabc',
        ]);

    $this->actingAs($user)
        ->post(route('watchlist.store'), [
            'network_id' => $network->id,
            'address' => '0xabc',
        ])
        ->assertSessionHasErrors('address');
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('watchlist.store'), [])
        ->assertSessionHasErrors(['network_id', 'address']);
});

test('store validates network exists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('watchlist.store'), [
            'network_id' => 999,
            'address' => '0xabc',
        ])
        ->assertSessionHasErrors('network_id');
});

// ── Update ──────────────────────────────────────────────────────────

test('update modifies pivot fields', function () {
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $user->wallets()->attach($wallet->id, ['custom_label' => 'Old']);

    $this->actingAs($user)
        ->patch(route('watchlist.update', $wallet), [
            'custom_label' => 'New Label',
            'is_notified' => true,
            'notify_threshold_usd' => 1000,
        ])
        ->assertRedirect();

    $pivot = $user->wallets()->where('wallet_id', $wallet->id)->first()->pivot;
    expect($pivot->custom_label)->toBe('New Label');
    expect((bool) $pivot->is_notified)->toBeTrue();
});

test('update rejects non-owner', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $other->wallets()->attach($wallet->id);

    $this->actingAs($user)
        ->patch(route('watchlist.update', $wallet), [
            'custom_label' => 'Hacked',
        ])
        ->assertForbidden();
});

// ── Destroy ─────────────────────────────────────────────────────────

test('destroy detaches wallet from user', function () {
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $user->wallets()->attach($wallet->id);

    $this->actingAs($user)
        ->delete(route('watchlist.destroy', $wallet))
        ->assertRedirect();

    expect($user->wallets()->count())->toBe(0);
    // Wallet record itself should still exist
    $this->assertDatabaseHas('wallets', ['id' => $wallet->id]);
});

test('destroy rejects non-owner', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $other->wallets()->attach($wallet->id);

    $this->actingAs($user)
        ->delete(route('watchlist.destroy', $wallet))
        ->assertForbidden();
});

// ── Update: new tracking settings ──────────────────────────────────

test('update accepts notify_direction field', function () {
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $user->wallets()->attach($wallet->id);

    $this->actingAs($user)
        ->patch(route('watchlist.update', $wallet), [
            'notify_direction' => 'incoming',
        ])
        ->assertRedirect();

    $pivot = $user->wallets()->where('wallet_id', $wallet->id)->first()->pivot;
    expect($pivot->notify_direction)->toBe('incoming');
});

test('update rejects invalid notify_direction', function () {
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $user->wallets()->attach($wallet->id);

    $this->actingAs($user)
        ->patch(route('watchlist.update', $wallet), [
            'notify_direction' => 'invalid',
        ])
        ->assertSessionHasErrors('notify_direction');
});

test('update accepts notify_cooldown_minutes', function () {
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $user->wallets()->attach($wallet->id);

    $this->actingAs($user)
        ->patch(route('watchlist.update', $wallet), [
            'notify_cooldown_minutes' => 60,
        ])
        ->assertRedirect();

    $pivot = $user->wallets()->where('wallet_id', $wallet->id)->first()->pivot;
    expect((int) $pivot->notify_cooldown_minutes)->toBe(60);
});

test('update rejects cooldown exceeding max', function () {
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $user->wallets()->attach($wallet->id);

    $this->actingAs($user)
        ->patch(route('watchlist.update', $wallet), [
            'notify_cooldown_minutes' => 99999,
        ])
        ->assertSessionHasErrors('notify_cooldown_minutes');
});

test('update accepts notes field', function () {
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $user->wallets()->attach($wallet->id);

    $this->actingAs($user)
        ->patch(route('watchlist.update', $wallet), [
            'notes' => 'This is a whale wallet I am watching.',
        ])
        ->assertRedirect();

    $pivot = $user->wallets()->where('wallet_id', $wallet->id)->first()->pivot;
    expect($pivot->notes)->toBe('This is a whale wallet I am watching.');
});

test('update rejects notes exceeding max length', function () {
    $user = User::factory()->create();
    $network = Network::factory()->ethereum()->create();
    $wallet = Wallet::factory()->for($network)->create();
    $user->wallets()->attach($wallet->id);

    $this->actingAs($user)
        ->patch(route('watchlist.update', $wallet), [
            'notes' => str_repeat('a', 5001),
        ])
        ->assertSessionHasErrors('notes');
});
