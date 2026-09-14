<?php

namespace Persona\Tests\Feature\Rules;

use Illuminate\Support\Facades\Validator;
use Persona\Persona;
use Persona\Rules\PersonaUniqueContactValue;
use Persona\Tests\TestCase;

class PersonaUniqueContactValueTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Cross-personable
    // -------------------------------------------------------------------------

    public function test_different_personables_can_register_the_same_value(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        Persona::for($userA)->addContact('email', 'shared@example.com');

        $ruleB = new PersonaUniqueContactValue('email', $userB);
        $validatorB = Validator::make(
            ['value' => 'SHARED@example.com'], // exercises normalization too
            ['value' => [$ruleB]]
        );

        $this->assertTrue($validatorB->passes(), 'Owner B should be allowed to register the same value Owner A holds');

        $contact = Persona::for($userB)->addContact('email', 'shared@example.com');

        $this->assertSame('shared@example.com', $contact->value);
    }

    // -------------------------------------------------------------------------
    // Duplicate detection
    // -------------------------------------------------------------------------

    public function test_same_personable_cannot_re_register_duplicate_value(): void
    {
        $user = $this->createUser();

        Persona::for($user)->addContact('email', 'dup@example.com');

        $rule = new PersonaUniqueContactValue('email', $user);
        $validator = Validator::make(
            ['value' => 'dup@example.com'],
            ['value' => [$rule]]
        );

        $this->assertTrue($validator->fails());
    }

    // -------------------------------------------------------------------------
    // Update scenario (ignorePersonableId)
    // -------------------------------------------------------------------------

    public function test_ignore_personable_id_allows_own_record_during_update(): void
    {
        $user = $this->createUser();
        $contact = Persona::for($user)->addContact('email', 'me@example.com');

        $rule = new PersonaUniqueContactValue('email', $user, ignorePersonableId: $user->getKey());
        $validator = Validator::make(
            ['value' => 'me@example.com'],
            ['value' => [$rule]]
        );

        $this->assertTrue($validator->passes(), 'Should not block the owner during update');
    }

    // -------------------------------------------------------------------------
    // Constructor guard
    // -------------------------------------------------------------------------

    public function test_non_model_personable_requires_explicit_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PersonaUniqueContactValue('email', 99);
    }

    public function test_non_model_personable_with_type_resolves_scope(): void
    {
        $user = $this->createUser();
        $type = $user->getMorphClass();
        $id = $user->getKey();

        $rule = new PersonaUniqueContactValue('email', $id, personableType: $type);

        $validator = Validator::make(
            ['value' => 'fresh@example.com'],
            ['value' => [$rule]]
        );

        $this->assertTrue($validator->passes());
    }
}