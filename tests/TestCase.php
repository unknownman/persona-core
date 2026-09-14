<?php

namespace Persona\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Persona\Providers\PersonaServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('x', 32)));
        $this->app['config']->set('persona.hash_key', '');

        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        $this->artisan('migrate', ['--database' => 'testing']);
    }

    protected function getPackageProviders($app): array
    {
        return [PersonaServiceProvider::class];
    }

    protected function createUser(int $id = 1): TestUser
    {
        return TestUser::create(['id' => $id]);
    }
}

class TestUser extends Model
{
    protected $guarded = [];

    protected $table = 'test_users';
}