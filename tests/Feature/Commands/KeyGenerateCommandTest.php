<?php

namespace Persona\Tests\Feature\Commands;

use Persona\Tests\TestCase;

class KeyGenerateCommandTest extends TestCase
{
    protected string $envDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envDir = sys_get_temp_dir().'/persona-key-'.uniqid();
        mkdir($this->envDir, 0777, true);

        $this->app->useEnvironmentPath($this->envDir);
    }

    protected function tearDown(): void
    {
        @unlink($this->envDir.'/.env');
        @rmdir($this->envDir);

        parent::tearDown();
    }

    public function test_appends_key_to_fresh_env_file(): void
    {
        file_put_contents($this->envDir.'/.env', "APP_NAME=Persona\nAPP_ENV=local\n");

        $this->artisan('persona:key')->assertSuccessful();

        $env = file_get_contents($this->envDir.'/.env');

        $this->assertStringContainsString('PERSONA_HASH_KEY=base64:', $env);
        $this->assertStringStartsWith("APP_NAME=Persona\nAPP_ENV=local", $env);
        $this->assertSame(1, substr_count($env, 'PERSONA_HASH_KEY='));
    }

    public function test_replaces_existing_key_in_env_file(): void
    {
        file_put_contents($this->envDir.'/.env', "PERSONA_HASH_KEY=base64:old\n");
        $this->app['config']->set('persona.hash_key', 'base64:old');

        $this->artisan('persona:key')
            ->expectsConfirmation('Persona hash key already set. Overwrite it?', 'yes')
            ->assertSuccessful();

        $env = file_get_contents($this->envDir.'/.env');

        $this->assertStringContainsString('PERSONA_HASH_KEY=base64:', $env);
        $this->assertStringNotContainsString('base64:old', $env);
        $this->assertSame(1, substr_count($env, 'PERSONA_HASH_KEY='));
    }
}
