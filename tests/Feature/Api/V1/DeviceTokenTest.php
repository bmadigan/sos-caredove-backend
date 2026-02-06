<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create(['team_id' => $this->team->id]);
    $this->token = $this->user->createToken('mobile-app')->plainTextToken;
});

it('stores a device token for the authenticated user', function () {
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-abc123',
            'platform' => 'ios',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.platform', 'ios');

    $this->assertDatabaseHas('device_tokens', [
        'user_id' => $this->user->id,
        'token' => 'fcm-token-abc123',
        'platform' => 'ios',
    ]);
});

it('upserts an existing device token', function () {
    DeviceToken::factory()->create([
        'user_id' => $this->user->id,
        'token' => 'fcm-token-abc123',
        'platform' => 'ios',
    ]);

    $otherUser = User::factory()->create(['team_id' => $this->team->id]);
    $otherToken = $otherUser->createToken('mobile-app')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$otherToken)
        ->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-abc123',
            'platform' => 'ios',
        ])
        ->assertSuccessful();

    // Token should now belong to the other user
    $this->assertDatabaseHas('device_tokens', [
        'token' => 'fcm-token-abc123',
        'user_id' => $otherUser->id,
    ]);

    $this->assertDatabaseCount('device_tokens', 1);
});

it('defaults platform to ios', function () {
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-xyz789',
        ])
        ->assertSuccessful();

    $this->assertDatabaseHas('device_tokens', [
        'token' => 'fcm-token-xyz789',
        'platform' => 'ios',
    ]);
});

it('rejects unauthenticated request', function () {
    $this->postJson('/api/v1/device-tokens', [
        'token' => 'fcm-token-abc123',
    ])->assertUnauthorized();
});

it('rejects request when team is inactive', function () {
    $this->team->update(['is_active' => false]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-abc123',
        ])
        ->assertForbidden();
});
