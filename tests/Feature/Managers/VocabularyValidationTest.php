<?php

namespace Persona\Tests\Feature\Managers;

use Persona\Persona;
use Persona\Tests\TestCase;

class VocabularyValidationTest extends TestCase
{
    public function test_invalid_contact_type_is_rejected_before_touching_db(): void
    {
        $user = $this->createUser();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed');

        Persona::for($user)->addContact('slack', 'alice@example.com');
    }

    public function test_invalid_address_type_is_rejected_before_touching_db(): void
    {
        $user = $this->createUser();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed');

        Persona::for($user)->addAddress('vacation_house', '1 Main St');
    }

    public function test_custom_contact_type_can_be_added_via_config(): void
    {
        $this->app['config']->set('persona.contact_types', array_merge(
            config('persona.contact_types', []),
            ['slack'],
        ));

        $user = $this->createUser();

        $contact = Persona::for($user)->addContact('slack', 'alice@example.com');

        $this->assertSame('slack', $contact->fresh()->type);
    }

    public function test_custom_address_type_can_be_added_via_config(): void
    {
        $this->app['config']->set('persona.address_types', array_merge(
            config('persona.address_types', []),
            ['vacation_house'],
        ));

        $user = $this->createUser();

        $address = Persona::for($user)->addAddress('vacation_house', '1 Main St');

        $this->assertSame('vacation_house', $address->fresh()->type);
    }
}