<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;

class TeamListCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'team:list';

    /**
     * @var string
     */
    protected $description = 'List all teams';

    public function handle(): int
    {
        $teams = Team::withCount('users')->get();

        if ($teams->isEmpty()) {
            $this->info('No teams found.');

            return Command::SUCCESS;
        }

        $this->table(
            ['Name', 'Slug', 'Invite Code', 'Active', 'Users', 'Slack Connected'],
            $teams->map(fn (Team $team) => [
                $team->name,
                $team->slug,
                $team->invite_code,
                $team->is_active ? 'Yes' : 'No',
                $team->users_count,
                $team->slack_team_id ? 'Yes' : 'No',
            ])
        );

        return Command::SUCCESS;
    }
}
