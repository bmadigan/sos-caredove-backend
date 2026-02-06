<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;

class TeamActivateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'team:activate {identifier : The slug or UUID of the team}';

    /**
     * @var string
     */
    protected $description = 'Activate a team';

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

        if ($team->is_active) {
            $this->warn("Team '{$team->name}' is already active.");

            return Command::SUCCESS;
        }

        $team->update(['is_active' => true]);

        $this->info("Team '{$team->name}' has been activated.");

        return Command::SUCCESS;
    }
}
