<?php

namespace Persona\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'persona:install {--migrations : Automatically run database migrations}';

    protected $description = 'Install Persona: publish assets, set the hash key, and run migrations.';

    public function handle(): int
    {
        $this->components->info('Publishing Persona configuration and migrations...');

        $this->call('vendor:publish', ['--tag' => 'persona-config']);
        $this->call('vendor:publish', ['--tag' => 'persona-migrations']);

        $this->publishCompanionAssets();

        $this->setupHashKey();

        if ($this->option('migrations') || ($this->input->isInteractive() && $this->components->confirm('Run the database migrations now?', false))) {
            $this->call('migrate');
        }

        $this->components->twoColumnDetail('Configuration', config_path('persona.php'));
        $this->components->twoColumnDetail('Migrations', database_path('migrations'));

        $this->components->info('Persona has been installed successfully.');

        return self::SUCCESS;
    }

    /**
     * Generate a PERSONA_HASH_KEY when none is configured.
     *
     * Drives the bundled `persona:key` command so a fresh install never runs
     * with an empty hash key (all encrypted/hashed Persona values would be
     * stored with a `base64:` prefix and every derived hash would be unstable).
     */
    private function setupHashKey(): void
    {
        if (config('persona.hash_key')) {
            return;
        }

        if (! $this->input->isInteractive()) {
            $this->call('persona:key');

            return;
        }

        if (! $this->components->confirm('Generate a Persona hash key now? (Recommended for encryption)', true)) {
            $this->components->warn('Skipped. Set PERSONA_HASH_KEY in your .env file before storing Persona values.');

            return;
        }

        $this->call('persona:key');
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
                'tags' => [
                    'persona-inertia-frontend',
                    'persona-inertia-controllers',
                    'persona-inertia-routes',
                    'persona-inertia-page',
                ],
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

            if (! $this->input->isInteractive() || ! $this->components->confirm($companion['question'], true)) {
                continue;
            }

            foreach ($companion['tags'] as $tag) {
                $this->call('vendor:publish', ['--tag' => $tag]);
            }
        }
    }
}