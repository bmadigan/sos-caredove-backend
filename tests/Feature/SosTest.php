<?php

use App\Models\DeviceToken;
use App\Models\Team;
use App\Models\User;
use App\Services\FCMService;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create(['team_id' => $this->team->id]);
    $this->recipient = User::factory()->create(['team_id' => $this->team->id]);
    DeviceToken::factory()->create(['user_id' => $this->recipient->id]);

    $this->mock(FCMService::class, function ($mock) {
        $mock->shouldReceive('sendSosAlert')->andReturn(['success' => 1, 'failure' => 0]);
    });
});

it('creates an sos alert and redirects back', function () {
    $this->actingAs($this->user)
        ->post(route('sos.store'), [
            'recipient_ids' => [$this->recipient->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('sos_alerts', [
        'team_id' => $this->team->id,
        'triggered_by' => $this->user->id,
    ]);
});

it('rejects recipients without device tokens', function () {
    $noDevice = User::factory()->create(['team_id' => $this->team->id]);

    $this->actingAs($this->user)
        ->post(route('sos.store'), [
            'recipient_ids' => [$noDevice->id],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('sos');

    $this->assertDatabaseCount('sos_alerts', 0);
});

it('rejects recipients from other teams', function () {
    $otherTeam = Team::factory()->create();
    $otherUser = User::factory()->create(['team_id' => $otherTeam->id]);
    DeviceToken::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($this->user)
        ->post(route('sos.store'), [
            'recipient_ids' => [$otherUser->id],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('sos');

    $this->assertDatabaseCount('sos_alerts', 0);
});

it('enforces rate limiting', function () {
    RateLimiter::hit('sos-alert:'.$this->team->id, 3600);
    RateLimiter::hit('sos-alert:'.$this->team->id, 3600);

    $this->actingAs($this->user)
        ->post(route('sos.store'), [
            'recipient_ids' => [$this->recipient->id],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('sos');

    $this->assertDatabaseCount('sos_alerts', 0);
});

it('requires at least one recipient', function () {
    $this->actingAs($this->user)
        ->post(route('sos.store'), [
            'recipient_ids' => [],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('recipient_ids');
});

it('rejects unauthenticated requests', function () {
    $this->post(route('sos.store'), [
        'recipient_ids' => [$this->recipient->id],
    ])->assertRedirect(route('login'));
});
