<?php

use App\Http\Controllers\Api\SlackOAuthController;
use App\Http\Controllers\Api\SlackWebhookController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\SosAlertController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\EnsureTeamIsActive;
use App\Http\Middleware\VerifySlackSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('sanctum.stateful')->group(function () {
    // Public auth routes
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Authenticated routes
    Route::middleware(['auth:sanctum', EnsureTeamIsActive::class])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('device-tokens', [DeviceTokenController::class, 'store']);
        Route::get('users', [UserController::class, 'index']);
        Route::get('sos-alerts', [SosAlertController::class, 'index']);
    });
});

// Slack routes (unversioned)
Route::prefix('slack')->group(function () {
    Route::get('install', [SlackOAuthController::class, 'install']);
    Route::get('oauth/callback', [SlackOAuthController::class, 'callback']);

    Route::middleware(VerifySlackSignature::class)->group(function () {
        Route::post('sos', [SlackWebhookController::class, 'slashCommand']);
        Route::post('interact', [SlackWebhookController::class, 'interact']);
    });
});
