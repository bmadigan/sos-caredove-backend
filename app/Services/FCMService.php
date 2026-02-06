<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FCMService
{
    public function __construct(
        protected Messaging $messaging,
    ) {}

    /**
     * Send SOS alert push notifications to the given device tokens.
     *
     * @param  Collection<int, DeviceToken>  $deviceTokens
     * @return array{success: int, failure: int}
     */
    public function sendSosAlert(Collection $deviceTokens, string $senderName): array
    {
        $tokens = $deviceTokens->pluck('token')->toArray();

        if (empty($tokens)) {
            return ['success' => 0, 'failure' => 0];
        }

        $message = CloudMessage::new()
            ->withNotification(Notification::create(
                title: 'SOS ALERT',
                body: "{$senderName} triggered an SOS alert",
            ))
            ->withData([
                'type' => 'sos_alert',
                'sender' => $senderName,
            ])
            ->withApnsConfig([
                'payload' => [
                    'aps' => [
                        'sound' => 'siren.caf',
                        'interruption-level' => 'time-sensitive',
                        'content-available' => 1,
                    ],
                ],
            ])
            ->withAndroidConfig([
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'sos_alerts',
                    'sound' => 'siren',
                ],
            ]);

        $report = $this->messaging->sendMulticast($message, $tokens);

        $this->handleFailedTokens($report, $deviceTokens);

        return [
            'success' => $report->successes()->count(),
            'failure' => $report->failures()->count(),
        ];
    }

    /**
     * Remove device tokens that FCM reports as invalid.
     *
     * @param  Collection<int, DeviceToken>  $deviceTokens
     */
    protected function handleFailedTokens(object $report, Collection $deviceTokens): void
    {
        $tokensByValue = $deviceTokens->keyBy('token');

        foreach ($report->failures()->getItems() as $failure) {
            $error = $failure->error();

            if ($error && in_array($error->getMessage(), ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'])) {
                $failedToken = $failure->target()->value();

                if (isset($tokensByValue[$failedToken])) {
                    $tokensByValue[$failedToken]->delete();

                    Log::info('Removed invalid FCM token', [
                        'token' => substr($failedToken, 0, 20).'...',
                        'reason' => $error->getMessage(),
                    ]);
                }
            }
        }
    }
}
