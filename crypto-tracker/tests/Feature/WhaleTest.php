<?php

use App\Models\Network;
use App\Models\User;
use App\Models\Wallet;

beforeEach(function () {
    $this->network = Network::factory()->create([
        'name' => 'Ethereum',
        'slug' => 'ethereum',
        'currency_symbol' => 'ETH',
        'explorer_url' => 'https://etherscan.io',
        'is_active' => true,
    ]);

    $this->bscNetwork = Network::factory()->create([
        'name' => 'Arbitrum',
        'slug' => 'arbitrum',
        'currency_symbol' => 'ETH',
        'explorer_url' => 'https://arbiscan.io',
        'is_active' => true,
    ]);
});

test('whales page is publicly accessible', function () {
    $this->get(route('whales'))
        ->assertOk();
});

test('whales page is accessible to authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('whales'))
        ->assertOk();
});

test('whales page shows whale wallets', function () {
    Wallet::factory()->create([
        'network_id' => $this->network->id,
        'address' => '0x28C6c06298d514Db089934071355E5743bf21d60',
        'is_whale' => true,
        'balance_usd' => 4_200_000_000,
        'metadata' => ['label' => 'Binance Hot Wallet'],
    ]);

    $this->get(route('whales'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Whales')
            ->has('whales', 1)
            ->where('whales.0.metadata.label', 'Binance Hot Wallet')
        );
});

test('whales page filters by network', function () {
    Wallet::factory()->create([
        'network_id' => $this->network->id,
        'address' => '0xETH1',
        'is_whale' => true,
        'balance_usd' => 1_000_000,
    ]);

    Wallet::factory()->create([
        'network_id' => $this->bscNetwork->id,
        'address' => '0xBSC1',
        'is_whale' => true,
        'balance_usd' => 2_000_000,
    ]);

    $this->get(route('whales', ['network' => 'ethereum']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Whales')
            ->has('whales', 1)
            ->where('whales.0.address', '0xETH1')
            ->where('activeNetwork', 'ethereum')
        );
});

test('whales page returns networks list', function () {
    $this->get(route('whales'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Whales')
            ->has('networks', 2)
        );
});

test('whales page excludes non-whale wallets', function () {
    Wallet::factory()->create([
        'network_id' => $this->network->id,
        'address' => '0xWhale',
        'is_whale' => true,
        'balance_usd' => 5_000_000_000,
    ]);

    Wallet::factory()->create([
        'network_id' => $this->network->id,
        'address' => '0xRegular',
        'is_whale' => false,
        'balance_usd' => 1_000,
    ]);

    $this->get(route('whales'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Whales')
            ->has('whales', 1)
            ->where('whales.0.address', '0xWhale')
        );
});

// ── trackedWhaleIds ────────────────────────────────────────────────

test('whales page returns empty trackedWhaleIds for guests', function () {
    $this->get(route('whales'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Whales')
            ->where('trackedWhaleIds', [])
        );
});

test('whales page returns trackedWhaleIds for authenticated users', function () {
    $user = User::factory()->create();
    $whale = Wallet::factory()->create([
        'network_id' => $this->network->id,
        'address' => '0xTrackedWhale',
        'is_whale' => true,
        'balance_usd' => 1_000_000,
    ]);

    $user->wallets()->attach($whale->id);

    $this->actingAs($user)
        ->get(route('whales'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Whales')
            ->where('trackedWhaleIds', [$whale->id])
        );
});

test('whales page does not include untracked wallet ids', function () {
    $user = User::factory()->create();
    Wallet::factory()->create([
        'network_id' => $this->network->id,
        'address' => '0xUntrackedWhale',
        'is_whale' => true,
        'balance_usd' => 2_000_000,
    ]);

    $this->actingAs($user)
        ->get(route('whales'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Whales')
            ->where('trackedWhaleIds', [])
        );
});
