<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use Illuminate\Console\Command;

class CleanInvalidDeviceTokens extends Command
{
    protected $signature = 'device-tokens:clean';

    protected $description = 'Remove device tokens that are not valid FCM registration tokens (e.g. raw APNs hex tokens)';

    public function handle(): int
    {
        $tokens = DeviceToken::all();
        $deleted = 0;

        foreach ($tokens as $token) {
            // FCM registration tokens are long (100+ chars) base64-ish strings
            // Raw APNs tokens are 64-char hex strings
            if (ctype_xdigit($token->token) && strlen($token->token) <= 64) {
                $this->line("Deleting APNs hex token: {$token->id} ({$token->platform}) — ".substr($token->token, 0, 20).'...');
                $token->delete();
                $deleted++;
            }
        }

        $remaining = DeviceToken::count();
        $this->info("Deleted {$deleted} invalid token(s). {$remaining} token(s) remaining.");

        return self::SUCCESS;
    }
}
