<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySlackSignature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signingSecret = config('services.slack.signing_secret');

        if (! $signingSecret) {
            return response()->json(['message' => 'Slack signing secret not configured.'], 500);
        }

        $timestamp = $request->header('X-Slack-Request-Timestamp');
        $signature = $request->header('X-Slack-Signature');

        if (! $timestamp || ! $signature) {
            return response()->json(['message' => 'Missing Slack signature headers.'], 401);
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return response()->json(['message' => 'Request timestamp is too old.'], 401);
        }

        $baseString = 'v0:'.$timestamp.':'.$request->getContent();
        $computedSignature = 'v0='.hash_hmac('sha256', $baseString, $signingSecret);

        if (! hash_equals($computedSignature, $signature)) {
            return response()->json(['message' => 'Invalid Slack signature.'], 401);
        }

        return $next($request);
    }
}
