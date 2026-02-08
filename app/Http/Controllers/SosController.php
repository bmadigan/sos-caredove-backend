<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSosRequest;
use App\Models\DeviceToken;
use App\Models\SosAlert;
use App\Models\User;
use App\Services\FCMService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;

class SosController extends Controller
{
    public function store(StoreSosRequest $request): RedirectResponse
    {
        $user = $request->user();
        $teamId = $user->team_id;

        // Rate limit: 5 alerts per team per hour
        $rateLimitKey = 'sos-alert:'.$teamId;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = (int) ceil($seconds / 60);

            return back()->withErrors([
                'sos' => "Rate limit exceeded. Try again in {$minutes} minutes.",
            ]);
        }

        // Validate recipients belong to this team and have device tokens
        $validRecipientIds = User::where('team_id', $teamId)
            ->whereIn('id', $request->recipient_ids)
            ->whereHas('deviceTokens')
            ->pluck('id')
            ->toArray();

        if (empty($validRecipientIds)) {
            return back()->withErrors([
                'sos' => 'None of the selected recipients have registered devices.',
            ]);
        }

        // Create SOS alert record
        SosAlert::create([
            'team_id' => $teamId,
            'triggered_by' => $user->id,
            'recipient_ids' => $validRecipientIds,
        ]);

        // Send FCM notifications
        $deviceTokens = DeviceToken::whereIn('user_id', $validRecipientIds)->get();
        $fcmService = app(FCMService::class);
        $result = $fcmService->sendSosAlert($deviceTokens, $user->name);

        // Only count against rate limit if at least one notification was sent
        if ($result['success'] > 0) {
            RateLimiter::hit($rateLimitKey, 3600);
        }

        return back()->with('success', 'SOS alert sent to '.count($validRecipientIds).' recipient(s).');
    }
}
