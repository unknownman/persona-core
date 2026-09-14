<?php

namespace Persona\Tests\Feature\Managers;

use Illuminate\Support\Facades\DB;
use Persona\Models\Contact;
use Persona\Persona;
use Persona\Support\PersonaHasher;
use Persona\Tests\TestCase;

class ContactManagerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Primary demotion
    // -------------------------------------------------------------------------

    public function test_adding_primary_contact_demotes_existing_primary_of_same_type(): void
    {
        $user = $this->createUser();

        $original = Persona::for($user)->addContact('email', 'old@example.com', isPrimary: true);

        $this->assertTrue($original->fresh()->is_primary, 'original should be primary');

        $new = Persona::for($user)->addContact('email', 'new@example.com', isPrimary: true);

        $this->assertTrue($new->fresh()->is_primary, 'new contact should be primary');
        $this->assertFalse($original->fresh()->is_primary, 'old contact should be demoted');

        $this->assertEquals(2, Contact::where('personable_type', $user->getMorphClass())
            ->where('personable_id', $user->getKey())
            ->where('type', 'email')
            ->count());
    }

    public function test_adding_non_primary_does_not_affect_existing_primary(): void
    {
        $user = $this->createUser();

        $primary = Persona::for($user)->addContact('email', 'primary@example.com', isPrimary: true);
        $secondary = Persona::for($user)->addContact('email', 'secondary@example.com');

        $this->assertTrue($primary->fresh()->is_primary);
        $this->assertFalse($secondary->fresh()->is_primary);
    }

    // -------------------------------------------------------------------------
    // Hashing
    // -------------------------------------------------------------------------

    public function test_raw_database_contains_hash_not_plain_text(): void
    {
        $user = $this->createUser();

        $plain = 'secret-contact-value';
        $contact = Persona::for($user)->addContact('phone', $plain);

        $raw = DB::table('persona_contacts')
            ->where('id', $contact->getKey())
            ->first();

        $this->assertNotNull($raw);
        $this->assertNotEquals($plain, $raw->value_hash);
        $this->assertEquals(PersonaHasher::hash($contact->fresh()->value), $raw->value_hash);
        $this->assertNotEquals($plain, $contact->fresh()->value);
    }

    public function test_different_values_produce_different_hashes(): void
    {
        $hash1 = PersonaHasher::hash('alpha');
        $hash2 = PersonaHasher::hash('beta');

        $this->assertNotEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // SHA-256 hex
    }

    // -------------------------------------------------------------------------
    // Soft-delete recovery
    // -------------------------------------------------------------------------

    public function test_soft_deleted_contact_is_restored_when_same_value_added_again(): void
    {
        $user = $this->createUser();

        $original = Persona::for($user)->addContact('email', 'dup@example.com', isPrimary: true);
        $originalId = $original->getKey();

        // Soft-delete the contact.
        $original->delete();

        $this->assertSoftDeleted('persona_contacts', ['id' => $originalId]);
        $this->assertTrue($original->fresh()->trashed());

        // Adding the same value again restores the trashed row instead of crashing.
        $restored = Persona::for($user)->addContact('email', 'dup@example.com');

        $this->assertEquals($originalId, $restored->getKey(), 'should restore the original row, not create a new one');
        $this->assertFalse($restored->fresh()->trashed(), 'restored contact is no longer soft-deleted');

        $this->assertEquals(1, Contact::where('personable_type', $user->getMorphClass())
            ->where('personable_id', $user->getKey())
            ->where('type', 'email')
            ->count());
    }

    public function test_soft_delete_recovery_preserves_hash_consistency(): void
    {
        $user = $this->createUser();

        $original = Persona::for($user)->addContact('phone', '+1555111222');
        $original->delete();

        $restored = Persona::for($user)->addContact('phone', '+1555111222');

        $raw = DB::table('persona_contacts')->where('id', $restored->getKey())->first();
        $this->assertEquals(PersonaHasher::hash('+1555111222'), $raw->value_hash);
    }

    // -------------------------------------------------------------------------
    // Uniqueness / active duplicate
    // -------------------------------------------------------------------------

    public function test_adding_duplicate_active_contact_throws(): void
    {
        $user = $this->createUser();

        Persona::for($user)->addContact('email', 'dup@example.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('already registered');

        Persona::for($user)->addContact('email', 'dup@example.com');
    }

    // -------------------------------------------------------------------------
    // IDOR protection
    // -------------------------------------------------------------------------

    public function test_delete_contact_not_owned_throws(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        $contact = Persona::for($userA)->addContact('email', 'a@example.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong');

        Persona::for($userB)->deleteContact($contact);
    }

    public function test_make_primary_contact_not_owned_throws(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        $contact = Persona::for($userA)->addContact('email', 'a@example.com');

        $this->expectException(\InvalidArgumentException::class);

        Persona::for($userB)->makeContactPrimary($contact);
    }

    public function test_verify_contact_not_owned_throws(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        $contact = Persona::for($userA)->addContact('email', 'a@example.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong');

        Persona::for($userB)->verifyContact($contact, '000000');
    }

    public function test_send_contact_verification_not_owned_throws(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        $contact = Persona::for($userA)->addContact('email', 'a@example.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong');

        Persona::for($userB)->sendContactVerification($contact);
    }

    public function test_verify_contact_with_valid_otp_marks_verified(): void
    {
        $user = $this->createUser();

        $contact = Persona::for($user)->addContact('email', 'verified@example.com');

        $otp = Persona::for($user)->sendContactVerification($contact);

        $this->assertTrue(Persona::for($user)->verifyContact($contact, $otp));
        $this->assertTrue($contact->fresh()->is_verified);
        $this->assertNotNull($contact->fresh()->verified_at);
    }

    public function test_domain_verify_requires_personable_and_throws_when_not_owned(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        $contact = Persona::for($userA)->addContact('email', 'a@example.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong');

        Persona::contacts()->verify($userB, $contact, '000000');
    }

    public function test_domain_send_verification_requires_personable_and_throws_when_not_owned(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        $contact = Persona::for($userA)->addContact('email', 'a@example.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong');

        Persona::contacts()->sendVerification($userB, $contact);
    }

    // -------------------------------------------------------------------------
    // Cross-type isolation
    // -------------------------------------------------------------------------

    public function test_same_value_across_different_types_does_not_conflict(): void
    {
        $user = $this->createUser();

        Persona::for($user)->addContact('email', 'ali@example.com');
        Persona::for($user)->addContact('handle', 'ali@example.com');

        $this->assertEquals(2, Contact::where('personable_type', $user->getMorphClass())
            ->where('personable_id', $user->getKey())
            ->count());
    }
}