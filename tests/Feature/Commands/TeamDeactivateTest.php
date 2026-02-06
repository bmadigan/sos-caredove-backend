<?php

use App\Models\Team;

it('deactivates a team by slug', function () {
    $team = Team::factory()->create(['slug' => 'test-team']);

    $this->artisan('team:deactivate', ['identifier' => 'test-team'])
        ->assertSuccessful();

    $team->refresh();
    expect($team->is_active)->toBeFalse();
});

it('deactivates a team by uuid', function () {
    $team = Team::factory()->create();

    $this->artisan('team:deactivate', ['identifier' => $team->id])
        ->assertSuccessful();

    $team->refresh();
    expect($team->is_active)->toBeFalse();
});

it('fails when team not found', function () {
    $this->artisan('team:deactivate', ['identifier' => 'nonexistent'])
        ->assertFailed();
});

it('warns when team already inactive', function () {
    $team = Team::factory()->inactive()->create(['slug' => 'inactive-team']);

    $this->artisan('team:deactivate', ['identifier' => 'inactive-team'])
        ->assertSuccessful();
});
