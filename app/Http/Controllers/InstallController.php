<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\SlackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InstallController extends Controller
{
    public function show(Request $request, SlackService $slackService): Response|JsonResponse
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

        return Inertia::render('install', [
            'team' => [
                'name' => $team->name,
                'invite_code' => $team->invite_code,
            ],
            'slackInstallUrl' => $slackService->getOAuthUrl($team->invite_code),
        ]);
    }
}
