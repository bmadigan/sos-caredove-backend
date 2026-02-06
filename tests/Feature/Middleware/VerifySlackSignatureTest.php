<?php

use App\Http\Middleware\VerifySlackSignature;
use Illuminate\Http\Request;

beforeEach(function () {
    config()->set('services.slack.signing_secret', 'test-secret-key');
});

it('passes with valid signature', function () {
    $body = 'token=xyzz0WbapA4vBCDEFasx0q6G&team_id=T1DC2JH3J';
    $timestamp = (string) time();
    $baseString = 'v0:'.$timestamp.':'.$body;
    $signature = 'v0='.hash_hmac('sha256', $baseString, 'test-secret-key');

    $request = Request::create('/api/slack/sos', 'POST', [], [], [], [
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_X_SLACK_SIGNATURE' => $signature,
    ], $body);

    $middleware = new VerifySlackSignature;

    $response = $middleware->handle($request, fn () => response('OK', 200));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('OK');
});

it('rejects missing signature headers', function () {
    $request = Request::create('/api/slack/sos', 'POST', [], [], [], [], 'body');

    $middleware = new VerifySlackSignature;
    $response = $middleware->handle($request, fn () => response('OK', 200));

    expect($response->getStatusCode())->toBe(401);
});

it('rejects expired timestamps', function () {
    $body = 'test-body';
    $timestamp = (string) (time() - 400); // 6+ minutes old
    $baseString = 'v0:'.$timestamp.':'.$body;
    $signature = 'v0='.hash_hmac('sha256', $baseString, 'test-secret-key');

    $request = Request::create('/api/slack/sos', 'POST', [], [], [], [
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_X_SLACK_SIGNATURE' => $signature,
    ], $body);

    $middleware = new VerifySlackSignature;
    $response = $middleware->handle($request, fn () => response('OK', 200));

    expect($response->getStatusCode())->toBe(401);
});

it('rejects invalid signature', function () {
    $body = 'test-body';
    $timestamp = (string) time();

    $request = Request::create('/api/slack/sos', 'POST', [], [], [], [
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $timestamp,
        'HTTP_X_SLACK_SIGNATURE' => 'v0=invalidsignature',
    ], $body);

    $middleware = new VerifySlackSignature;
    $response = $middleware->handle($request, fn () => response('OK', 200));

    expect($response->getStatusCode())->toBe(401);
});

it('returns 500 when signing secret not configured', function () {
    config()->set('services.slack.signing_secret', null);

    $request = Request::create('/api/slack/sos', 'POST', [], [], [], [
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) time(),
        'HTTP_X_SLACK_SIGNATURE' => 'v0=test',
    ], 'body');

    $middleware = new VerifySlackSignature;
    $response = $middleware->handle($request, fn () => response('OK', 200));

    expect($response->getStatusCode())->toBe(500);
});
