<?php

namespace App\Http\Controllers;

use App\Models\SosAlert;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $teamId = $request->user()->team_id;

        $users = User::where('team_id', $teamId)
            ->withCount('deviceTokens')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'has_device_token' => $user->device_tokens_count > 0,
                'created_at' => $user->created_at->toIso8601String(),
            ]);

        $sosAlerts = SosAlert::where('team_id', $teamId)
            ->with('triggeredBy')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (SosAlert $alert) => [
                'id' => $alert->id,
                'triggered_by_name' => $alert->triggeredBy?->name ?? $alert->slack_user_name ?? 'Unknown',
                'recipient_count' => is_array($alert->recipient_ids) ? count($alert->recipient_ids) : 0,
                'created_at' => $alert->created_at->toIso8601String(),
            ]);

        $usersWithDevices = $users->where('has_device_token', true)->count();

        return Inertia::render('dashboard', [
            'users' => $users,
            'sosAlerts' => $sosAlerts,
            'stats' => [
                'totalUsers' => $users->count(),
                'devicesRegistered' => $usersWithDevices,
                'alertsToday' => SosAlert::where('team_id', $teamId)
                    ->whereDate('created_at', today())
                    ->count(),
            ],
        ]);
    }
}
