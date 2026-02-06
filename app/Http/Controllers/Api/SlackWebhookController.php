<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\SosAlert;
use App\Models\Team;
use App\Models\User;
use App\Services\FCMService;
use App\Services\SlackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

class SlackWebhookController extends Controller
{
    public function __construct(
        protected SlackService $slackService,
        protected FCMService $fcmService,
    ) {}

    /**
     * Handle the /sos slash command from Slack.
     */
    public function slashCommand(Request $request): Response
    {
        $slackTeamId = $request->input('team_id');
        $triggerId = $request->input('trigger_id');
        $userId = $request->input('user_id');
        $userName = $request->input('user_name');

        $team = Team::where('slack_team_id', $slackTeamId)->first();

        if (! $team) {
            return $this->ephemeralResponse('This workspace is not linked to an SOS team.');
        }

        if (! $team->is_active) {
            return $this->ephemeralResponse('Your team has been deactivated.');
        }

        // Rate limit: 2 alerts per team per hour
        $rateLimitKey = 'sos-alert:'.$team->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 2)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = (int) ceil($seconds / 60);

            return $this->ephemeralResponse("Rate limit exceeded. Try again in {$minutes} minutes.");
        }

        // Get team members who have registered device tokens
        $usersWithDevices = User::where('team_id', $team->id)
            ->whereHas('deviceTokens')
            ->get();

        if ($usersWithDevices->isEmpty()) {
            return $this->ephemeralResponse('No team members have registered their devices yet.');
        }

        // Open the recipient selection modal
        $modal = $this->slackService->buildRecipientSelectModal($usersWithDevices, $team->id);
        $this->slackService->openModal($triggerId, $team->slack_bot_token, $modal);

        return response('', 200);
    }

    /**
     * Handle modal submission interactions from Slack.
     */
    public function interact(Request $request): Response|\Illuminate\Http\JsonResponse
    {
        $payload = json_decode($request->input('payload'), true);

        if (! $payload || ($payload['type'] ?? '') !== 'view_submission') {
            return response('', 200);
        }

        $callbackId = $payload['view']['callback_id'] ?? '';

        if ($callbackId !== 'sos_send') {
            return response('', 200);
        }

        $teamId = $payload['view']['private_metadata'] ?? null;
        $team = Team::find($teamId);

        if (! $team || ! $team->is_active) {
            return response('', 200);
        }

        // Extract selected recipients
        $values = $payload['view']['state']['values'] ?? [];
        $selectedRecipientIds = $values['recipients_block']['selected_recipients']['selected_options'] ?? [];
        $selectedRecipientIds = array_map(fn ($option) => $option['value'], $selectedRecipientIds);

        // Check if "Select All" was checked
        $selectAllValues = $values['select_all_block']['select_all']['selected_options'] ?? [];
        $selectAll = ! empty($selectAllValues);

        if ($selectAll) {
            $selectedRecipientIds = User::where('team_id', $team->id)
                ->whereHas('deviceTokens')
                ->pluck('id')
                ->toArray();
        }

        if (empty($selectedRecipientIds)) {
            return response('', 200);
        }

        // Validate all recipient IDs belong to this team (NFR-5)
        $validRecipients = User::where('team_id', $team->id)
            ->whereIn('id', $selectedRecipientIds)
            ->pluck('id')
            ->toArray();

        $selectedRecipientIds = array_intersect($selectedRecipientIds, $validRecipients);

        if (empty($selectedRecipientIds)) {
            return response('', 200);
        }

        // Get sender info from Slack payload
        $slackUserId = $payload['user']['id'] ?? null;
        $slackUserName = $payload['user']['name'] ?? $payload['user']['username'] ?? 'Someone';

        // Log the SOS alert
        SosAlert::create([
            'team_id' => $team->id,
            'recipient_ids' => $selectedRecipientIds,
            'slack_user_id' => $slackUserId,
            'slack_user_name' => $slackUserName,
            'channel_id' => $payload['view']['app_id'] ?? null,
        ]);

        // Hit the rate limiter
        RateLimiter::hit('sos-alert:'.$team->id, 3600);

        // Dispatch FCM notifications
        $deviceTokens = DeviceToken::whereIn('user_id', $selectedRecipientIds)->get();
        $this->fcmService->sendSosAlert($deviceTokens, $slackUserName);

        // Return confirmation modal
        $confirmationModal = $this->slackService->buildConfirmationModal(count($selectedRecipientIds));

        return response()->json([
            'response_action' => 'update',
            'view' => $confirmationModal,
        ]);
    }

    /**
     * Build an ephemeral Slack response.
     */
    protected function ephemeralResponse(string $text): Response
    {
        return response(json_encode([
            'response_type' => 'ephemeral',
            'text' => $text,
        ]), 200, ['Content-Type' => 'application/json']);
    }
}
