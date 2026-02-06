<?php

use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create(['invite_code' => 'TESTCODE']);
});

it('registers a user with a valid invite code', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invite_code' => 'TESTCODE',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'team'],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'team_id' => $this->team->id,
    ]);
});

it('rejects registration with an invalid invite code', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invite_code' => 'BADCODE1',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('invite_code');
});

it('rejects registration with an inactive team invite code', function () {
    $this->team->update(['is_active' => false]);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invite_code' => 'TESTCODE',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('invite_code');
});

it('rejects registration with a duplicate email', function () {
    User::factory()->create(['email' => 'john@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invite_code' => 'TESTCODE',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('logs in with valid credentials', function () {
    $user = User::factory()->create([
        'team_id' => $this->team->id,
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email', 'team'],
        ]);
});

it('rejects login with invalid credentials', function () {
    $user = User::factory()->create([
        'team_id' => $this->team->id,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable();
});

it('rejects login when team is deactivated', function () {
    $this->team->update(['is_active' => false]);

    $user = User::factory()->create([
        'team_id' => $this->team->id,
        'password' => 'password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertForbidden();
});

it('logs out and revokes token', function () {
    $user = User::factory()->create(['team_id' => $this->team->id]);
    $token = $user->createToken('mobile-app')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('returns 401 for unauthenticated logout', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertUnauthorized();
});
