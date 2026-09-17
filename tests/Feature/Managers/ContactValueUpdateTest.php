<?php

namespace Persona\Tests\Feature\Managers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Persona\Events\ContactValueUpdated;
use Persona\Models\Contact;
use Persona\Persona;
use Persona\Tests\TestCase;

class ContactValueUpdateTest extends TestCase
{
    public function test_updating_value_resets_verification_and_dispatches_event(): void
    {
        $user = $this->createUser();
        $contact = Persona::for($user)->addContact('email', 'a@example.com');

        Cache::put("persona:otp:{$contact->getKey()}", '123456', now()->addMinute());
        $this->assertTrue(Persona::for($user)->verifyContact($contact, '123456'));
        $this->assertTrue($contact->fresh()->is_verified);

        Event::fake();

        $updated = Persona::for($user)->updateContactValue($contact, 'b@example.com');

        $this->assertSame('b@example.com', $updated->refresh()->value);
        $this->assertFalse($updated->is_verified);
        $this->assertNull($updated->verified_at);

        Event::assertDispatched(
            ContactValueUpdated::class,
            fn (ContactValueUpdated $event) => $event->contact->is($updated)
                && $event->oldValue === 'a@example.com'
                && $event->newValue === 'b@example.com'
        );
    }

    public function test_updating_to_an_existing_active_value_throws(): void
    {
        $user = $this->createUser();

        Persona::for($user)->addContact('email', 'dup@example.com');
        $other = Persona::for($user)->addContact('email', 'other@example.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already registered');

        Persona::for($user)->updateContactValue($other, 'DUP@EXAMPLE.COM');
    }

    public function test_updating_to_a_trashed_twin_value_restores_the_twin(): void
    {
        $user = $this->createUser();

        $twin = Persona::for($user)->addContact('email', 'twin@example.com');
        Persona::for($user)->deleteContact($twin);

        $current = Persona::for($user)->addContact(
            'email',
            'current@example.com',
            isPrimary: true,
            isEmergency: true,
        );

        Event::fake();

        $restored = Persona::for($user)->updateContactValue($current, 'TWIN@example.com');

        $this->assertSame($twin->getKey(), $restored->getKey());
        $this->assertFalse($restored->trashed());
        $this->assertTrue($restored->fresh()->is_primary);
        $this->assertTrue($restored->fresh()->is_emergency);
        $this->assertTrue($current->fresh()->trashed());
        $this->assertEquals(1, Contact::where('personable_type', $user->getMorphClass())
            ->where('personable_id', $user->getKey())
            ->whereNull('deleted_at')
            ->count());

        Event::assertDispatched(
            ContactValueUpdated::class,
            fn (ContactValueUpdated $event) => $event->contact->is($restored)
                && $event->oldValue === 'current@example.com'
                && $event->newValue === 'twin@example.com'
        );
    }
}