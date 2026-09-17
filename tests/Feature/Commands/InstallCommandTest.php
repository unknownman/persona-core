<?php

namespace Persona\Tests\Feature\Commands;

use Persona\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    protected string $envDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envDir = sys_get_temp_dir().'/persona-install-'.uniqid();
        mkdir($this->envDir, 0777, true);
        file_put_contents($this->envDir.'/.env', "APP_NAME=Persona\nAPP_ENV=local\n");

        $this->app->useEnvironmentPath($this->envDir);
    }

    protected function tearDown(): void
    {
        @unlink($this->envDir.'/.env');
        @rmdir($this->envDir);

        parent::tearDown();
    }

    public function test_install_generates_a_hash_key_when_none_is_set(): void
    {
        $this->artisan('persona:install', ['--no-interaction' => true])
            ->assertSuccessful();

        $env = file_get_contents($this->envDir.'/.env');

        $this->assertStringContainsString('PERSONA_HASH_KEY=base64:', $env);
        $this->assertSame(1, substr_count($env, 'PERSONA_HASH_KEY='));
    }

    public function test_install_skips_key_generation_when_hash_key_is_already_set(): void
    {
        $this->app['config']->set('persona.hash_key', 'base64:existing');

        $this->artisan('persona:install', ['--no-interaction' => true])
            ->assertSuccessful();

        $env = file_get_contents($this->envDir.'/.env');

        $this->assertStringNotContainsString('PERSONA_HASH_KEY', $env);
    }
}