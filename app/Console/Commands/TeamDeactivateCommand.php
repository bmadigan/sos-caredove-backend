<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;

class TeamDeactivateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'team:deactivate {identifier : The slug or UUID of the team}';

    /**
     * @var string
     */
    protected $description = 'Deactivate a team';

    public function handle(): int
    {
        $identifier = $this->argument('identifier');

        $team = Team::where('slug', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        if (! $team) {
            $this->error("Team not found: {$identifier}");

            return Command::FAILURE;
        }

        if (! $team->is_active) {
            $this->warn("Team '{$team->name}' is already inactive.");

            return Command::SUCCESS;
        }

        $team->update(['is_active' => false]);

        $this->info("Team '{$team->name}' has been deactivated.");

        return Command::SUCCESS;
    }
}
