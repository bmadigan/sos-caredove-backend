<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
        $credentialsPath = config('firebase.projects.app.credentials');
        $this->line("Credentials config: {$credentialsPath}");

        if (is_string($credentialsPath) && file_exists(base_path($credentialsPath))) {
            $json = json_decode(file_get_contents(base_path($credentialsPath)), true);
            $this->info('File exists: YES');
            $this->line('  project_id: '.($json['project_id'] ?? 'MISSING'));
            $this->line('  client_email: '.($json['client_email'] ?? 'MISSING'));
            $this->line('  private_key_id: '.($json['private_key_id'] ?? 'MISSING'));
            $this->line('  private_key starts with: '.substr($json['private_key'] ?? '', 0, 30).'...');
        } elseif (is_string($credentialsPath)) {
            $this->error('File NOT found at: '.base_path($credentialsPath));

            return self::FAILURE;
        } else {
            $this->error('Credentials not configured');

            return self::FAILURE;
        }

        // 2. Test OAuth2 token generation
        $this->newLine();
        $this->info('Testing OAuth2 token generation...');

        try {
            $credentials = new \Google\Auth\Credentials\ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/firebase.messaging'],
                json_decode(file_get_contents(base_path($credentialsPath)), true)
            );

            $token = $credentials->fetchAuthToken();

            if (! empty($token['access_token'])) {
                $this->info('OAuth2 token generated successfully!');
                $this->line('  Token prefix: '.substr($token['access_token'], 0, 30).'...');
                $this->line('  Expires in: '.($token['expires_in'] ?? 'unknown').' seconds');
            } else {
                $this->error('OAuth2 token generation returned empty token');
                $this->line('  Response: '.json_encode($token));

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('OAuth2 token generation FAILED: '.$e->getMessage());
            $this->line('  Class: '.get_class($e));

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

        // 4. Optionally send a test message
        $fcmToken = $this->option('token');
        if ($fcmToken) {
            $this->newLine();
            $this->info('Sending test notification to: '.substr($fcmToken, 0, 20).'...');

            try {
                $message = CloudMessage::withTarget('token', $fcmToken)
                    ->withNotification(Notification::create(
                        title: 'Firebase Test',
                        body: 'If you see this, FCM is working!',
                    ));

                $result = $messaging->send($message);
                $this->info('Send succeeded! Response: '.json_encode($result));
            } catch (\Throwable $e) {
                $this->error('Send FAILED: '.$e->getMessage());
                $this->line('  Class: '.get_class($e));

                if ($previous = $e->getPrevious()) {
                    $this->line('  Caused by: '.$previous->getMessage());
                    $this->line('  Caused by class: '.get_class($previous));
                }
            }
        } else {
            $this->newLine();
            $this->line('Tip: pass --token=<FCM_TOKEN> to send a test notification');
        }

        return self::SUCCESS;
    }
}
