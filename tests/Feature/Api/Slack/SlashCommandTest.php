<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    config()->set('services.slack.signing_secret', 'test-signing-secret');

    $this->team = Team::factory()->withSlack()->create();
    $this->user = User::factory()->create(['team_id' => $this->team->id]);
    DeviceToken::factory()->create(['user_id' => $this->user->id]);
});

function postSlackSos(mixed $testCase, array $params): \Illuminate\Testing\TestResponse
{
    $body = http_build_query($params);
    $timestamp = (string) time();
    $baseString = 'v0:'.$timestamp.':'.$body;
    $signature = 'v0='.hash_hmac('sha256', $baseString, 'test-signing-secret');

    return $testCase->call('POST', '/api/slack/sos', $params, [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_X_SLACK_SIGNATURE' => $signature,
    ], $body);
}

it('opens modal for valid slash command', function () {
    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('buildRecipientSelectModal')->once()->andReturn(['type' => 'modal']);
        $mock->shouldReceive('openModal')->once()->andReturn(true);
    });

    postSlackSos($this, [
        'team_id' => $this->team->slack_team_id,
        'trigger_id' => 'test-trigger',
        'user_id' => 'U123',
        'user_name' => 'testuser',
    ])->assertOk();
});

it('returns error for unknown workspace', function () {
    $response = postSlackSos($this, [
        'team_id' => 'T_UNKNOWN',
        'trigger_id' => 'test-trigger',
        'user_id' => 'U123',
        'user_name' => 'testuser',
    ]);

    $response->assertOk();
    expect($response->json('text'))->toContain('not linked');
});

it('returns error for inactive team', function () {
    $this->team->update(['is_active' => false]);

    $response = postSlackSos($this, [
        'team_id' => $this->team->slack_team_id,
        'trigger_id' => 'test-trigger',
        'user_id' => 'U123',
        'user_name' => 'testuser',
    ]);

    $response->assertOk();
    expect($response->json('text'))->toContain('deactivated');
});

it('enforces rate limiting', function () {
    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('buildRecipientSelectModal')->andReturn(['type' => 'modal']);
        $mock->shouldReceive('openModal')->andReturn(true);
    });

    // Hit the rate limit
    RateLimiter::hit('sos-alert:'.$this->team->id, 3600);
    RateLimiter::hit('sos-alert:'.$this->team->id, 3600);

    $response = postSlackSos($this, [
        'team_id' => $this->team->slack_team_id,
        'trigger_id' => 'test-trigger',
        'user_id' => 'U123',
        'user_name' => 'testuser',
    ]);

    $response->assertOk();
    expect($response->json('text'))->toContain('Rate limit');
});

it('returns error when no team members have devices', function () {
    DeviceToken::query()->delete();

    $response = postSlackSos($this, [
        'team_id' => $this->team->slack_team_id,
        'trigger_id' => 'test-trigger',
        'user_id' => 'U123',
        'user_name' => 'testuser',
    ]);

    $response->assertOk();
    expect($response->json('text'))->toContain('No team members');
});
