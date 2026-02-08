<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSosRequest;
use App\Models\DeviceToken;
use App\Models\SosAlert;
use App\Models\User;
use App\Services\FCMService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SosController extends Controller
{
    public function store(StoreSosRequest $request): RedirectResponse
    {
        $user = $request->user();
        $teamId = $user->team_id;

        // Rate limit: 25 alerts per team per hour
        $rateLimitKey = 'sos-alert:'.$teamId;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 25)) {
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

        Log::info('SOS: sending FCM notifications', [
            'sender' => $user->name,
            'recipient_count' => count($validRecipientIds),
            'device_token_count' => $deviceTokens->count(),
            'tokens' => $deviceTokens->map(fn ($dt) => [
                'platform' => $dt->platform,
                'token_prefix' => substr($dt->token, 0, 20).'...',
            ]),
        ]);

        $fcmService = app(FCMService::class);
        $result = $fcmService->sendSosAlert($deviceTokens, $user->name);

        Log::info('SOS: FCM result', $result);

        // Only count against rate limit if at least one notification was sent
        if ($result['success'] > 0) {
            RateLimiter::hit($rateLimitKey, 3600);
        }

        return back()->with('success', 'SOS alert sent to '.count($validRecipientIds).' recipient(s).');
    }
}
