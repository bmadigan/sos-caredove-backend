<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseDiagnostic extends Command
{
    protected $signature = 'firebase:diagnostic {--token= : FCM token to send a test message to}';

    protected $description = 'Diagnose Firebase configuration and FCM connectivity';

    public function handle(): int
    {
        $this->info('=== Firebase Diagnostic ===');

        // 1. Check credentials config
        $credentials = config('firebase.projects.app.credentials');

        if (! is_string($credentials) || empty($credentials)) {
            $this->error('Credentials not configured');

            return self::FAILURE;
        }

        // Detect format: JSON string (raw or decoded from base64) vs file path
        if (str_starts_with($credentials, '{')) {
            $this->info('Credentials format: JSON string (inline or base64-decoded)');
            $json = json_decode($credentials, true);

            if (! $json) {
                $this->error('Invalid JSON in credentials');

                return self::FAILURE;
            }
        } else {
            $this->info("Credentials format: file path — {$credentials}");
            $fullPath = str_starts_with($credentials, '/') ? $credentials : base_path($credentials);

            if (! file_exists($fullPath)) {
                $this->error("File NOT found at: {$fullPath}");

                return self::FAILURE;
            }

            $json = json_decode(file_get_contents($fullPath), true);
        }
        $projectId = $json['project_id'] ?? 'MISSING';
        $this->info('File exists: YES');
        $this->line("  project_id: {$projectId}");
        $this->line('  client_email: '.($json['client_email'] ?? 'MISSING'));
        $this->line('  private_key_id: '.($json['private_key_id'] ?? 'MISSING'));

        // 2. Test OAuth2 token generation
        $this->newLine();
        $this->info('Testing OAuth2 token generation...');

        try {
            $credentials = new \Google\Auth\Credentials\ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/firebase.messaging'],
                $json,
            );

            $authToken = $credentials->fetchAuthToken();

            if (empty($authToken['access_token'])) {
                $this->error('OAuth2 token generation returned empty token');

                return self::FAILURE;
            }

            $accessToken = $authToken['access_token'];
            $this->info('OAuth2 token generated successfully!');
            $this->line('  Token prefix: '.substr($accessToken, 0, 30).'...');
        } catch (\Throwable $e) {
            $this->error('OAuth2 FAILED: '.$e->getMessage());

            return self::FAILURE;
        }

        // 3. Test Messaging service resolution
        $this->newLine();
        $this->info('Resolving Firebase Messaging service...');

        try {
            $messaging = app(Messaging::class);
            $this->info('Messaging service resolved: '.get_class($messaging));
        } catch (\Throwable $e) {
            $this->error('Failed to resolve Messaging: '.$e->getMessage());

            return self::FAILURE;
        }

        // 4. Get FCM token (from option or database)
        $fcmToken = $this->option('token');
        if (! $fcmToken) {
            $deviceToken = \App\Models\DeviceToken::first();
            if (! $deviceToken) {
                $this->newLine();
                $this->error('No device tokens in database. Register a device first.');

                return self::FAILURE;
            }
            $fcmToken = $deviceToken->token;
            $this->newLine();
            $this->line("Using device token from DB: {$deviceToken->platform} — ".substr($fcmToken, 0, 20).'...');
            $this->line('Full token length: '.strlen($fcmToken).' chars');
        }

        // 4a. Direct HTTP send (bypasses kreait SDK)
        $this->newLine();
        $this->info('=== Test A: Direct HTTP to FCM API ===');
        $this->line('Sending to: '.substr($fcmToken, 0, 20).'...');

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $this->line("URL: {$url}");

        $response = Http::withToken($accessToken)
            ->post($url, [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => 'Direct HTTP Test',
                        'body' => 'Sent directly via HTTP, bypassing kreait SDK',
                    ],
                ],
            ]);

        if ($response->successful()) {
            $this->info('DIRECT HTTP: SUCCESS!');
            $this->line('  Response: '.$response->body());
        } else {
            $this->error('DIRECT HTTP: FAILED ('.$response->status().')');
            $this->line('  Response: '.$response->body());
        }

        // 4b. kreait SDK send
        $this->newLine();
        $this->info('=== Test B: kreait SDK send ===');

        try {
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification(Notification::create(
                    title: 'SDK Test',
                    body: 'Sent via kreait SDK',
                ));

            $result = $messaging->send($message);
            $this->info('SDK: SUCCESS!');
            $this->line('  Response: '.json_encode($result));
        } catch (\Throwable $e) {
            $this->error('SDK: FAILED — '.$e->getMessage());

            if ($previous = $e->getPrevious()) {
                $this->line('  Caused by: '.$previous->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
