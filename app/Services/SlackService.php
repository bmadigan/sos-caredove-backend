<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class SlackService
{
    /**
     * Build the Slack OAuth authorization URL.
     */
    public function getOAuthUrl(string $inviteCode): string
    {
        return 'https://slack.com/oauth/v2/authorize?'.http_build_query([
            'client_id' => config('services.slack.client_id'),
            'scope' => config('services.slack.scopes'),
            'redirect_uri' => config('services.slack.redirect_uri'),
            'state' => $inviteCode,
        ]);
    }

    /**
     * Exchange an OAuth authorization code for access tokens.
     *
     * @return array{ok: bool, access_token?: string, team?: array{id: string, name: string}, error?: string}
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->post('https://slack.com/api/oauth.v2.access', [
            'client_id' => config('services.slack.client_id'),
            'client_secret' => config('services.slack.client_secret'),
            'code' => $code,
            'redirect_uri' => config('services.slack.redirect_uri'),
        ]);

        return $response->json();
    }

    /**
     * Open a modal in Slack using the views.open API.
     */
    public function openModal(string $triggerId, string $botToken, array $viewPayload): bool
    {
        $response = Http::withToken($botToken)->post('https://slack.com/api/views.open', [
            'trigger_id' => $triggerId,
            'view' => $viewPayload,
        ]);

        return $response->json('ok', false);
    }

    /**
     * Update a modal in Slack using the views.update API.
     */
    public function updateModal(string $viewId, string $botToken, array $viewPayload): bool
    {
        $response = Http::withToken($botToken)->post('https://slack.com/api/views.update', [
            'view_id' => $viewId,
            'view' => $viewPayload,
        ]);

        return $response->json('ok', false);
    }

    /**
     * Build a Block Kit modal for selecting SOS recipients.
     *
     * @param  Collection<int, \App\Models\User>  $users
     * @return array<string, mixed>
     */
    public function buildRecipientSelectModal(Collection $users, string $teamId): array
    {
        $options = $users->map(fn ($user) => [
            'text' => [
                'type' => 'plain_text',
                'text' => $user->name,
            ],
            'value' => $user->id,
        ])->toArray();

        return [
            'type' => 'modal',
            'callback_id' => 'sos_send',
            'private_metadata' => $teamId,
            'title' => [
                'type' => 'plain_text',
                'text' => 'Send SOS Alert',
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => 'Send SOS',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Cancel',
            ],
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => 'Select the team members you want to alert:',
                    ],
                ],
                [
                    'type' => 'actions',
                    'block_id' => 'select_all_block',
                    'elements' => [
                        [
                            'type' => 'checkboxes',
                            'action_id' => 'select_all',
                            'options' => [
                                [
                                    'text' => [
                                        'type' => 'plain_text',
                                        'text' => 'Select All',
                                    ],
                                    'value' => 'all',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'recipients_block',
                    'element' => [
                        'type' => 'multi_static_select',
                        'action_id' => 'selected_recipients',
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => 'Choose recipients',
                        ],
                        'options' => $options,
                    ],
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Recipients',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a Block Kit confirmation modal.
     *
     * @return array<string, mixed>
     */
    public function buildConfirmationModal(int $recipientCount): array
    {
        return [
            'type' => 'modal',
            'title' => [
                'type' => 'plain_text',
                'text' => 'SOS Sent',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Done',
            ],
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*SOS alert sent successfully!*\n\n{$recipientCount} ".($recipientCount === 1 ? 'person was' : 'people were').' alerted.',
                    ],
                ],
            ],
        ];
    }
}
