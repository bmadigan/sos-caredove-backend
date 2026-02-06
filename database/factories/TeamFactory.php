<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'invite_code' => strtoupper(Str::random(8)),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the team is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the team has Slack connected.
     */
    public function withSlack(): static
    {
        return $this->state(fn (array $attributes) => [
            'slack_team_id' => 'T'.strtoupper(Str::random(10)),
            'slack_team_name' => $attributes['name'].' Workspace',
            'slack_bot_token' => 'xoxb-'.Str::random(50),
        ]);
    }
}
