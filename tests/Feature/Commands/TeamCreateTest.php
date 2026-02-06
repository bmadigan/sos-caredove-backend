<?php

use App\Models\Team;

it('creates a team with name slug and invite code', function () {
    $this->artisan('team:create', ['name' => 'Acme Corp'])
        ->assertSuccessful();

    $this->assertDatabaseHas('teams', [
        'name' => 'Acme Corp',
        'slug' => 'acme-corp',
        'is_active' => true,
    ]);

    $team = Team::where('name', 'Acme Corp')->first();
    expect($team->invite_code)->toHaveLength(8);
});

it('generates unique slug when duplicate exists', function () {
    Team::factory()->create(['slug' => 'acme-corp']);

    $this->artisan('team:create', ['name' => 'Acme Corp'])
        ->assertSuccessful();

    $teams = Team::where('name', 'Acme Corp')->get();
    expect($teams)->toHaveCount(1);
    expect($teams->first()->slug)->toStartWith('acme-corp-');
});
