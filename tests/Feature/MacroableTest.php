<?php

namespace Persona\Tests\Feature;

use Persona\Managers\PersonaManager;
use Persona\Managers\ScopedPersonaManager;
use Persona\Persona;
use Persona\Tests\TestCase;

class MacroableTest extends TestCase
{
    protected function tearDown(): void
    {
        PersonaManager::flushMacros();
        ScopedPersonaManager::flushMacros();

        parent::tearDown();
    }

    public function test_host_can_define_a_persona_manager_macro(): void
    {
        PersonaManager::macro('exportToCsv', fn (): string => 'csv-dump');

        $this->assertSame('csv-dump', Persona::manager()->exportToCsv());
    }

    public function test_host_macro_resolves_through_the_static_entry_point(): void
    {
        PersonaManager::macro('exportToCsv', fn (): string => 'csv-dump');

        $this->assertSame('csv-dump', Persona::exportToCsv());
    }

    public function test_host_can_define_a_scoped_persona_manager_macro(): void
    {
        ScopedPersonaManager::macro('exportCsv', fn (string $label): string => "csv:{$label}");

        $user = $this->createUser();

        $this->assertSame('csv:ali', Persona::for($user)->exportCsv('ali'));
    }

    public function test_macro_access_is_forwarded_on_the_manager(): void
    {
        PersonaManager::macro('echoValue', fn ($value) => $value);

        $this->assertTrue(PersonaManager::hasMacro('echoValue'));
        $this->assertSame(42, Persona::manager()->echoValue(42));
    }
}