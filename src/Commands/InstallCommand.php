<?php

namespace Persona\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'persona:install {--migrations : Automatically run database migrations}';

    protected $description = 'Install and configure the Persona core package.';

    public function handle(): int
    {
        $this->components->info('Publishing Persona configuration and migrations...');

        $this->call('vendor:publish', ['--tag' => 'persona-config', '--force' => true]);
        $this->call('vendor:publish', ['--tag' => 'persona-migrations', '--force' => true]);

        if ($this->option('migrations') || $this->components->confirm('Run the database migrations now?', false)) {
            $this->call('migrate');
        }

        $this->components->twoColumnDetail('Configuration', config_path('persona.php'));
        $this->components->twoColumnDetail('Migrations', database_path('migrations'));

        $this->components->info('Persona has been installed successfully.');

        return self::SUCCESS;
    }
}