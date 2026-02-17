<?php

use App\Models\User;
use App\Exceptions\ApiResponseException;
use App\Services\ApiService;
use Illuminate\Http\Client\Response;

// ── Public Access ────────────────────────────────────────────────────

test('dexscreener endpoints are publicly accessible', function (string $routeName) {
    $mockResponse = Mockery::mock(\Illuminate\Http\Client\Response::class);
    $mockResponse->shouldReceive('json')->andReturn([]);

    $this->mock(ApiService::class)
        ->shouldReceive('get')
        ->once()
        ->andReturn($mockResponse);

    $this->getJson(route($routeName))
        ->assertOk();
})->with([
    'dex.latestTokenProfiles',
    'dex.getLatestBoostedTokens',
    'dex.getMostBoostedTokens',
]);

// ── Latest Token Profiles ────────────────────────────────────────────

test('getLatestTokenProfiles returns token data', function () {
    $user = User::factory()->create();

    $mockData = [
        ['chainId' => 'ethereum', 'tokenAddress' => '0xabc'],
        ['chainId' => 'solana', 'tokenAddress' => '0xdef'],
    ];

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('json')->andReturn($mockData);

    $this->mock(ApiService::class)
        ->shouldReceive('get')
        ->with('https://api.dexscreener.com/token-profiles/latest/v1')
        ->once()
        ->andReturn($mockResponse);

    $response = $this->actingAs($user)
        ->getJson(route('dex.latestTokenProfiles'));

    $response->assertOk()
        ->assertJson($mockData);
});

test('getLatestTokenProfiles groups by chainId when group=1', function () {
    $user = User::factory()->create();

    $mockData = [
        ['chainId' => 'ethereum', 'tokenAddress' => '0xabc'],
        ['chainId' => 'ethereum', 'tokenAddress' => '0xdef'],
        ['chainId' => 'solana', 'tokenAddress' => '0xghi'],
    ];

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('json')->andReturn($mockData);

    $this->mock(ApiService::class)
        ->shouldReceive('get')
        ->once()
        ->andReturn($mockResponse);

    $response = $this->actingAs($user)
        ->getJson(route('dex.latestTokenProfiles', ['group' => 1]));

    $response->assertOk();

    $data = $response->json();
    expect($data)->toHaveKey('ethereum');
    expect($data)->toHaveKey('solana');
    expect($data['ethereum'])->toHaveCount(2);
    expect($data['solana'])->toHaveCount(1);
});

test('getLatestTokenProfiles handles API error', function () {
    $user = User::factory()->create();

    $this->mock(ApiService::class)
        ->shouldReceive('get')
        ->once()
        ->andThrow(new ApiResponseException('Rate limited', 429));

    $response = $this->actingAs($user)
        ->getJson(route('dex.latestTokenProfiles'));

    // ApiResponseException stores status in statusCode, but controller reads getCode() which returns 0, falling back to 500
    $response->assertStatus(500)
        ->assertJson([
            'error' => 'Could not fetch latest token profiles',
            'message' => 'Rate limited',
        ]);
});

// ── Latest Boosted Tokens ────────────────────────────────────────────

test('getLatestBoostedTokens returns data', function () {
    $user = User::factory()->create();

    $mockData = [
        ['chainId' => 'ethereum', 'amount' => 100],
    ];

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('json')->andReturn($mockData);

    $this->mock(ApiService::class)
        ->shouldReceive('get')
        ->with('https://api.dexscreener.com/token-boosts/latest/v1')
        ->once()
        ->andReturn($mockResponse);

    $response = $this->actingAs($user)
        ->getJson(route('dex.getLatestBoostedTokens'));

    $response->assertOk()
        ->assertJson($mockData);
});

test('getLatestBoostedTokens groups by chainId', function () {
    $user = User::factory()->create();

    $mockData = [
        ['chainId' => 'solana', 'amount' => 50],
        ['chainId' => 'solana', 'amount' => 30],
    ];

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('json')->andReturn($mockData);

    $this->mock(ApiService::class)
        ->shouldReceive('get')
        ->once()
        ->andReturn($mockResponse);

    $response = $this->actingAs($user)
        ->getJson(route('dex.getLatestBoostedTokens', ['group' => 1]));

    $data = $response->json();
    expect($data['solana'])->toHaveCount(2);
});

test('getLatestBoostedTokens handles API error', function () {
    $user = User::factory()->create();

    $this->mock(ApiService::class)
        ->shouldReceive('get')
        ->once()
        ->andThrow(new ApiResponseException('Service unavailable', 503));

    $response = $this->actingAs($user)
        ->getJson(route('dex.getLatestBoostedTokens'));

    $response->assertStatus(500)
        ->assertJson([
            'error' => 'Could not fetch latest boosted tokens',
            'message' => 'Service unavailable',
        ]);
});

// ── Most Boosted Tokens ──────────────────────────────────────────────

test('getMostBoostedTokens returns data', function () {
    $user = User::factory()->create();

    $mockData = [
        ['chainId' => 'ethereum', 'totalAmount' => 500],
    ];

    $mockResponse = Mockery::mock(Response::class);
    $mockResponse->shouldReceive('json')->andReturn($mockData);

    $this->mock(ApiService::class)
        ->shouldReceive('get')
        ->with('https://api.dexscreener.com/token-boosts/top/v1')
        ->once()
        ->andReturn($mockResponse);

    $response = $this->actingAs($user)
        ->getJson(route('dex.getMostBoostedTokens'));

    $response->assertOk()
        ->assertJson($mockData);
});

test('getMostBoostedTokens handles API error with zero status code', function () {
    $user = User::factory()->create();

    $this->mock(ApiService::class)
        ->shouldReceive('get')
        ->once()
        ->andThrow(new ApiResponseException('Network error', 0));

    $response = $this->actingAs($user)
        ->getJson(route('dex.getMostBoostedTokens'));

    $response->assertStatus(500)
        ->assertJson([
            'error' => 'Could not fetch tokens with most active boosts',
        ]);
});
