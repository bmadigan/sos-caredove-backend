<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TeamCreateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'team:create {name : The name of the team}';

    /**
     * @var string
     */
    protected $description = 'Create a new team with an invite code';

    public function handle(): int
    {
        $name = $this->argument('name');
        $slug = Str::slug($name);

        // Ensure slug is unique
        if (Team::where('slug', $slug)->exists()) {
            $slug .= '-'.strtolower(Str::random(4));
        }

        $team = Team::create([
            'name' => $name,
            'slug' => $slug,
            'invite_code' => Team::generateInviteCode(),
        ]);

        $this->info('Team created successfully!');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Name', $team->name],
            ['Slug', $team->slug],
            ['Invite Code', $team->invite_code],
            ['Install URL', url('/install?invite_code='.$team->invite_code)],
        ]);

        return Command::SUCCESS;
    }
}
