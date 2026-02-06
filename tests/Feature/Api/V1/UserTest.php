<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create(['team_id' => $this->team->id]);
    $this->token = $this->user->createToken('mobile-app')->plainTextToken;
});

it('returns users from the same team', function () {
    $teammate = User::factory()->create(['team_id' => $this->team->id]);
    DeviceToken::factory()->create(['user_id' => $teammate->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/users');

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('does not return users from other teams', function () {
    $otherTeam = Team::factory()->create();
    User::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/users');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');

    $ids = collect($response->json('data'))->pluck('id')->toArray();
    expect($ids)->toContain($this->user->id);
});

it('includes device token presence', function () {
    DeviceToken::factory()->create(['user_id' => $this->user->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/users');

    $response->assertSuccessful();

    $userData = collect($response->json('data'))->firstWhere('id', $this->user->id);
    expect($userData['has_device_token'])->toBeTrue();
});

it('rejects unauthenticated request', function () {
    $this->getJson('/api/v1/users')->assertUnauthorized();
});
