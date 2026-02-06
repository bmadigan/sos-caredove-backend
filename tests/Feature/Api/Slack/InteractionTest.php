<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Services\FCMService;
use App\Services\SlackService;

beforeEach(function () {
    config()->set('services.slack.signing_secret', 'test-signing-secret');

    $this->team = Team::factory()->withSlack()->create();
    $this->user = User::factory()->create(['team_id' => $this->team->id]);
    DeviceToken::factory()->create(['user_id' => $this->user->id]);

    // Mock both services to prevent real API calls
    $this->mock(FCMService::class, function ($mock) {
        $mock->shouldReceive('sendSosAlert')->andReturn(['success' => 1, 'failure' => 0]);
    });
    $this->mock(SlackService::class, function ($mock) {
        $mock->shouldReceive('buildConfirmationModal')->andReturn(['type' => 'modal']);
    });
});

function makeInteractionPayload(string $teamId, array $selectedUserIds): array
{
    $options = array_map(fn ($id) => ['value' => $id], $selectedUserIds);

    return [
        'type' => 'view_submission',
        'user' => [
            'id' => 'U123',
            'name' => 'testuser',
        ],
        'view' => [
            'callback_id' => 'sos_send',
            'private_metadata' => $teamId,
            'app_id' => 'A123',
            'state' => [
                'values' => [
                    'recipients_block' => [
                        'selected_recipients' => [
                            'selected_options' => $options,
                        ],
                    ],
                    'select_all_block' => [
                        'select_all' => [
                            'selected_options' => [],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

function postInteraction(mixed $testCase, array $params): \Illuminate\Testing\TestResponse
{
    $body = http_build_query($params);
    $timestamp = (string) time();
    $baseString = 'v0:'.$timestamp.':'.$body;
    $signature = 'v0='.hash_hmac('sha256', $baseString, 'test-signing-secret');

    return $testCase->call('POST', '/api/slack/interact', $params, [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_X_SLACK_SIGNATURE' => $signature,
    ], $body);
}

it('processes modal submission and creates sos alert', function () {
    $payload = makeInteractionPayload($this->team->id, [$this->user->id]);

    postInteraction($this, ['payload' => json_encode($payload)])->assertOk();

    $this->assertDatabaseHas('sos_alerts', [
        'team_id' => $this->team->id,
    ]);
});

it('rejects recipients from other teams', function () {
    $otherTeam = Team::factory()->create();
    $otherUser = User::factory()->create(['team_id' => $otherTeam->id]);

    $payload = makeInteractionPayload($this->team->id, [$otherUser->id]);

    postInteraction($this, ['payload' => json_encode($payload)])->assertOk();

    $this->assertDatabaseCount('sos_alerts', 0);
});

it('ignores non view_submission payloads', function () {
    postInteraction($this, ['payload' => json_encode(['type' => 'block_actions', 'actions' => []])])->assertOk();

    $this->assertDatabaseCount('sos_alerts', 0);
});

it('ignores submission for inactive team', function () {
    $this->team->update(['is_active' => false]);

    $payload = makeInteractionPayload($this->team->id, [$this->user->id]);

    postInteraction($this, ['payload' => json_encode($payload)])->assertOk();

    $this->assertDatabaseCount('sos_alerts', 0);
});
