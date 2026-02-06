<?php

use App\Models\Team;
use App\Models\User;

it('generates an 8 character invite code', function () {
    $code = Team::generateInviteCode();

    expect($code)->toHaveLength(8);
    expect($code)->toMatch('/^[A-Z0-9]{8}$/');
});

it('encrypts the slack bot token', function () {
    $team = Team::factory()->create([
        'slack_bot_token' => 'xoxb-test-token-value',
    ]);

    // Raw DB value should be encrypted (not the plain text)
    $raw = \DB::table('teams')->where('id', $team->id)->value('slack_bot_token');
    expect($raw)->not->toBe('xoxb-test-token-value');

    // But model should decrypt it
    $team->refresh();
    expect($team->slack_bot_token)->toBe('xoxb-test-token-value');
});

it('hides slack bot token from serialization', function () {
    $team = Team::factory()->create([
        'slack_bot_token' => 'xoxb-secret-token',
    ]);

    $array = $team->toArray();
    expect($array)->not->toHaveKey('slack_bot_token');
});

it('has users relationship', function () {
    $team = Team::factory()->create();
    User::factory()->count(3)->create(['team_id' => $team->id]);

    expect($team->users)->toHaveCount(3);
});

it('scopes to active teams', function () {
    Team::factory()->count(2)->create(['is_active' => true]);
    Team::factory()->inactive()->create();

    expect(Team::active()->count())->toBe(2);
});

it('casts is_active as boolean', function () {
    $team = Team::factory()->create(['is_active' => true]);
    expect($team->is_active)->toBeTrue()->toBeBool();
});
