<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class UserCreateCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'user:create
        {email : The user email address}
        {--name= : The user name (defaults to email username)}
        {--team= : The team slug to assign the user to}
        {--password= : The user password (defaults to "password")}';

    /**
     * @var string
     */
    protected $description = 'Create a new user and assign to a team';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->option('name') ?? ucfirst(explode('@', $email)[0]);
        $password = $this->option('password') ?? 'password';
        $teamSlug = $this->option('team');

        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists.");

            return Command::FAILURE;
        }

        if ($teamSlug) {
            $team = Team::where('slug', $teamSlug)->first();
            if (! $team) {
                $this->error("Team with slug '{$teamSlug}' not found.");

                return Command::FAILURE;
            }
        } else {
            $team = Team::first();
            if (! $team) {
                $this->error('No teams exist. Create a team first: php artisan team:create <name>');

                return Command::FAILURE;
            }
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'team_id' => $team->id,
            'email_verified_at' => now(),
        ]);

        $this->info('User created successfully!');
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Name', $user->name],
            ['Email', $user->email],
            ['Team', $team->name],
            ['Password', $password],
        ]);

        return Command::SUCCESS;
    }
}
