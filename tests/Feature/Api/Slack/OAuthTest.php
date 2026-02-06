<?php

use App\Models\Team;
use App\Services\SlackService;

beforeEach(function () {
    $this->team = Team::factory()->create(['invite_code' => 'TESTINST']);
});

it('redirects to slack oauth with valid invite code', function () {
    $response = $this->getJson('/api/slack/install?invite_code=TESTINST');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('slack.com/oauth');
});

it('returns error for missing invite code', function () {
    $this->getJson('/api/slack/install')
        ->assertStatus(400)
        ->assertJsonPath('message', 'Missing invite code.');
});

it('returns error for invalid invite code', function () {
    $this->getJson('/api/slack/install?invite_code=BADCODE1')
        ->assertNotFound();
});

it('returns error for inactive team invite code', function () {
    $this->team->update(['is_active' => false]);

    $this->getJson('/api/slack/install?invite_code=TESTINST')
        ->assertNotFound();
});

it('processes oauth callback and stores bot token', function () {
    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('exchangeCode')
            ->with('test-code')
            ->andReturn([
                'ok' => true,
                'access_token' => 'xoxb-test-bot-token',
                'team' => [
                    'id' => 'T_SLACK_123',
                    'name' => 'Test Workspace',
                ],
            ]);
    });

    $response = $this->getJson('/api/slack/oauth/callback?code=test-code&state=TESTINST');

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Slack app installed successfully.');

    $this->team->refresh();
    expect($this->team->slack_team_id)->toBe('T_SLACK_123');
    expect($this->team->slack_team_name)->toBe('Test Workspace');
});

it('prevents linking workspace to different team', function () {
    // Create another team already linked to this workspace
    Team::factory()->create(['slack_team_id' => 'T_SLACK_123']);

    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('exchangeCode')
            ->andReturn([
                'ok' => true,
                'access_token' => 'xoxb-test-bot-token',
                'team' => [
                    'id' => 'T_SLACK_123',
                    'name' => 'Test Workspace',
                ],
            ]);
    });

    $this->getJson('/api/slack/oauth/callback?code=test-code&state=TESTINST')
        ->assertStatus(409);
});

it('handles oauth error from slack', function () {
    $this->getJson('/api/slack/oauth/callback?error=access_denied')
        ->assertStatus(400)
        ->assertJsonPath('message', 'Slack installation was cancelled.');
});

it('allows re-installation on same team', function () {
    $this->team->update([
        'slack_team_id' => 'T_SLACK_123',
        'slack_team_name' => 'Old Name',
    ]);

    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('exchangeCode')
            ->andReturn([
                'ok' => true,
                'access_token' => 'xoxb-new-token',
                'team' => [
                    'id' => 'T_SLACK_123',
                    'name' => 'Updated Name',
                ],
            ]);
    });

    $this->getJson('/api/slack/oauth/callback?code=new-code&state=TESTINST')
        ->assertSuccessful();

    $this->team->refresh();
    expect($this->team->slack_team_name)->toBe('Updated Name');
});
