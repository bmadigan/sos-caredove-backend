<?php

use App\Models\DeviceToken;
use App\Models\SosAlert;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create(['team_id' => $this->team->id]);
});

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('returns users scoped to the authenticated user team', function () {
    $teammate = User::factory()->create(['team_id' => $this->team->id]);
    $otherTeam = Team::factory()->create();
    User::factory()->create(['team_id' => $otherTeam->id]);

    $response = $this->actingAs($this->user)
        ->get(route('dashboard'));

    $response->assertOk();
    $users = $response->original->getData()['page']['props']['users'];
    expect($users)->toHaveCount(2);
});

it('shows device registration status for users', function () {
    DeviceToken::factory()->create(['user_id' => $this->user->id]);

    $response = $this->actingAs($this->user)
        ->get(route('dashboard'));

    $users = $response->original->getData()['page']['props']['users'];
    $currentUser = collect($users)->firstWhere('id', $this->user->id);
    expect($currentUser['has_device_token'])->toBeTrue();
});

it('returns sos alerts scoped to team', function () {
    SosAlert::factory()->count(3)->create(['team_id' => $this->team->id]);

    $otherTeam = Team::factory()->create();
    SosAlert::factory()->count(2)->create(['team_id' => $otherTeam->id]);

    $response = $this->actingAs($this->user)
        ->get(route('dashboard'));

    $sosAlerts = $response->original->getData()['page']['props']['sosAlerts'];
    expect($sosAlerts)->toHaveCount(3);
});

it('returns correct stats', function () {
    $teammate = User::factory()->create(['team_id' => $this->team->id]);
    DeviceToken::factory()->create(['user_id' => $this->user->id]);
    SosAlert::factory()->create(['team_id' => $this->team->id]);

    $response = $this->actingAs($this->user)
        ->get(route('dashboard'));

    $stats = $response->original->getData()['page']['props']['stats'];
    expect($stats['totalUsers'])->toBe(2)
        ->and($stats['devicesRegistered'])->toBe(1)
        ->and($stats['alertsToday'])->toBe(1);
});
