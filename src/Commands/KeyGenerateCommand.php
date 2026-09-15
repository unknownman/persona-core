<?php

namespace Persona\Commands;

use Illuminate\Console\Command;

class KeyGenerateCommand extends Command
{
    protected $signature = 'persona:key {--show : Display the key instead of modifying files}';

    protected $description = 'Set the Persona hash key in the environment file.';

    public function handle(): int
    {
        $key = $this->generateRandomKey();

        if ($this->option('show')) {
            $this->components->line('<comment>'.$key.'</comment>');

            return self::SUCCESS;
        }

        if (file_exists($this->envPath()) === false) {
            $this->components->error('.env file not found.');

            return self::FAILURE;
        }

        if (! $this->isConfirmed()) {
            $this->components->warn('Command cancelled. No changes were made.');

            return self::SUCCESS;
        }

        $this->writeNewEnvironmentFileWith($key);

        $this->components->info('Persona hash key set successfully.');

        return self::SUCCESS;
    }

    /**
     * Generate a random key for the application.
     */
    protected function generateRandomKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /**
     * Write the environment file with the new key.
     */
    protected function writeNewEnvironmentFileWith(string $key): void
    {
        $envContent = file_get_contents($this->envPath());

        if (! str_contains($envContent, 'PERSONA_HASH_KEY')) {
            file_put_contents(
                $this->envPath(),
                $envContent.PHP_EOL.'PERSONA_HASH_KEY='.$key.PHP_EOL
            );

            return;
        }

        file_put_contents($this->envPath(), preg_replace(
            $this->keyReplacementPattern(),
            'PERSONA_HASH_KEY='.$key,
            $envContent
        ));
    }

    /**
     * Get the regex pattern that will replace the PERSONA_HASH_KEY variable.
     */
    protected function keyReplacementPattern(): string
    {
        $escaped = preg_quote('='.$this->laravel['config']['persona.hash_key'], '/');

        return "/^PERSONA_HASH_KEY{$escaped}/m";
    }

    /**
     * Get the environment file path.
     */
    protected function envPath(): string
    {
        if ($this->laravel->environmentPath() === base_path()) {
            return $this->laravel->environmentFile();
        }

        return $this->laravel->environmentPath().'/'.$this->laravel->environmentFile();
    }

    /**
     * Confirm before overwriting an existing key.
     */
    protected function isConfirmed(): bool
    {
        $existing = $this->laravel['config']['persona.hash_key'];

        if ($existing === '' || $existing === 'base64:') {
            return true;
        }

        return $this->components->confirm('Persona hash key already set. Overwrite it?', false);
    }
}