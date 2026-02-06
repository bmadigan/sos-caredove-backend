<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\SlackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SlackOAuthController extends Controller
{
    public function __construct(
        protected SlackService $slackService,
    ) {}

    /**
     * Redirect to Slack's OAuth consent screen.
     */
    public function install(Request $request): RedirectResponse|JsonResponse
    {
        $inviteCode = $request->query('invite_code');

        if (! $inviteCode) {
            return response()->json(['message' => 'Missing invite code.'], 400);
        }

        $team = Team::where('invite_code', strtoupper($inviteCode))
            ->where('is_active', true)
            ->first();

        if (! $team) {
            return response()->json(['message' => 'Invalid or inactive invite code.'], 404);
        }

        return redirect($this->slackService->getOAuthUrl($team->invite_code));
    }

    /**
     * Handle the Slack OAuth callback.
     */
    public function callback(Request $request): JsonResponse
    {
        if ($request->has('error')) {
            return response()->json([
                'message' => 'Slack installation was cancelled.',
                'error' => $request->input('error'),
            ], 400);
        }

        $code = $request->input('code');
        $inviteCode = $request->input('state');

        if (! $code || ! $inviteCode) {
            return response()->json(['message' => 'Missing authorization code or state.'], 400);
        }

        $team = Team::where('invite_code', strtoupper($inviteCode))
            ->where('is_active', true)
            ->first();

        if (! $team) {
            return response()->json(['message' => 'Invalid invite code.'], 404);
        }

        $oauthResponse = $this->slackService->exchangeCode($code);

        if (! ($oauthResponse['ok'] ?? false)) {
            return response()->json([
                'message' => 'Failed to complete Slack OAuth.',
                'error' => $oauthResponse['error'] ?? 'unknown',
            ], 400);
        }

        $slackTeamId = $oauthResponse['team']['id'] ?? null;
        $slackTeamName = $oauthResponse['team']['name'] ?? null;
        $botToken = $oauthResponse['access_token'] ?? null;

        // Check if this Slack workspace is already linked to a different team
        $existingTeam = Team::where('slack_team_id', $slackTeamId)
            ->where('id', '!=', $team->id)
            ->first();

        if ($existingTeam) {
            return response()->json([
                'message' => 'This Slack workspace is already linked to another team.',
            ], 409);
        }

        // Update the team record (upsert for re-installation)
        $team->update([
            'slack_team_id' => $slackTeamId,
            'slack_team_name' => $slackTeamName,
            'slack_bot_token' => $botToken,
        ]);

        return response()->json([
            'message' => 'Slack app installed successfully.',
            'team_name' => $team->name,
            'invite_code' => $team->invite_code,
        ]);
    }
}
