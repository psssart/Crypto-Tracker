<?php

use App\Models\User;

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
