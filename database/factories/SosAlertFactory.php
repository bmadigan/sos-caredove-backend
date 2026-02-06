<?php

namespace Database\Factories;

use App\Models\SosAlert;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SosAlert>
 */
class SosAlertFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'triggered_by' => User::factory(),
            'recipient_ids' => [fake()->uuid(), fake()->uuid()],
            'slack_user_id' => 'U'.strtoupper(fake()->bothify('??########')),
            'slack_user_name' => fake()->userName(),
            'channel_id' => 'C'.strtoupper(fake()->bothify('??########')),
        ];
    }
}
