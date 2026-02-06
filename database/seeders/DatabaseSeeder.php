<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $team = Team::factory()->create([
            'name' => 'Demo Team',
            'slug' => 'demo-team',
            'invite_code' => 'DEMO1234',
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'team_id' => $team->id,
        ]);
    }
}
