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

        $this->publishCompanionAssets();

        if ($this->option('migrations') || $this->components->confirm('Run the database migrations now?', false)) {
            $this->call('migrate');
        }

        $this->components->twoColumnDetail('Configuration', config_path('persona.php'));
        $this->components->twoColumnDetail('Migrations', database_path('migrations'));

        $this->components->info('Persona has been installed successfully.');

        return self::SUCCESS;
    }

    private function publishCompanionAssets(): void
    {
        $companions = [
            [
                'provider' => \Persona\Livewire\Providers\PersonaLivewireServiceProvider::class,
                'question' => 'Persona Livewire detected. Publish Livewire components and views? [Y/n]',
                'tags' => ['persona-livewire-views'],
            ],
            [
                'provider' => \Persona\Blade\Providers\PersonaBladeServiceProvider::class,
                'question' => 'Persona Blade detected. Publish Blade views? [Y/n]',
                'tags' => ['persona-blade-views'],
            ],
            [
                'provider' => \Persona\Inertia\Providers\PersonaInertiaServiceProvider::class,
                'question' => 'Persona Inertia detected. Publish frontend assets and controllers? [Y/n]',
                'tags' => ['persona-inertia-frontend', 'persona-inertia-controllers'],
            ],
            [
                'provider' => \Persona\Api\Providers\PersonaApiServiceProvider::class,
                'question' => 'Persona API detected. Publish API stubs? [Y/n]',
                'tags' => ['persona-api-stubs'],
            ],
        ];

        foreach ($companions as $companion) {
            if (! class_exists($companion['provider'])) {
                continue;
            }

            if (! $this->components->confirm($companion['question'], true)) {
                continue;
            }

            foreach ($companion['tags'] as $tag) {
                $this->call('vendor:publish', ['--tag' => $tag]);
            }
        }
    }
}