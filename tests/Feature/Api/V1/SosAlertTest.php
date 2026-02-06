<?php

use App\Models\SosAlert;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create(['team_id' => $this->team->id]);
    $this->token = $this->user->createToken('mobile-app')->plainTextToken;
});

it('returns alerts scoped to the user team', function () {
    SosAlert::factory()->count(3)->create(['team_id' => $this->team->id]);

    $otherTeam = Team::factory()->create();
    SosAlert::factory()->count(2)->create(['team_id' => $otherTeam->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/sos-alerts');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

it('returns a maximum of 20 alerts', function () {
    SosAlert::factory()->count(25)->create(['team_id' => $this->team->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/sos-alerts');

    $response->assertSuccessful()
        ->assertJsonCount(20, 'data');
});

it('returns alerts ordered by newest first', function () {
    $oldest = SosAlert::factory()->create([
        'team_id' => $this->team->id,
        'created_at' => now()->subHour(),
    ]);

    $newest = SosAlert::factory()->create([
        'team_id' => $this->team->id,
        'created_at' => now(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/sos-alerts');

    $response->assertSuccessful();

    $alertIds = collect($response->json('data'))->pluck('id')->toArray();
    expect($alertIds[0])->toBe($newest->id);
    expect($alertIds[1])->toBe($oldest->id);
});

it('does not return alerts from other teams', function () {
    $otherTeam = Team::factory()->create();
    SosAlert::factory()->count(3)->create(['team_id' => $otherTeam->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/sos-alerts');

    $response->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

it('rejects unauthenticated request', function () {
    $this->getJson('/api/v1/sos-alerts')->assertUnauthorized();
});
