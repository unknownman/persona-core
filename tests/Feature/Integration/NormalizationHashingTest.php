<?php

namespace Persona\Tests\Feature\Integration;

use Illuminate\Support\Facades\DB;
use Persona\Casts\LookupHash;
use Persona\Models\Contact;
use Persona\Persona;
use Persona\Support\PersonaHasher;
use Persona\Tests\TestCase;
use Persona\Tests\TestUser;

class NormalizationHashingTest extends TestCase
{
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser();
    }

    private function firstContact(): Contact
    {
        return Contact::where('personable_type', $this->user->getMorphClass())
            ->where('personable_id', $this->user->getKey())
            ->firstOrFail();
    }

    private function contactByHash(string $hash): ?Contact
    {
        return Contact::where('personable_type', $this->user->getMorphClass())
            ->where('personable_id', $this->user->getKey())
            ->where('value_hash', $hash)
            ->first();
    }

    public function test_email_is_normalized_to_lowercase_before_storage(): void
    {
        Persona::for($this->user)->addContact('email', '  ALICE@Example.COM  ');

        $this->assertEquals('alice@example.com', $this->firstContact()->value);
    }

    public function test_phone_is_stripped_to_digits_before_storage(): void
    {
        Persona::for($this->user)->addContact('phone', '(555) 123-4567');

        $this->assertEquals('5551234567', $this->firstContact()->value);
    }

    public function test_phone_with_plus_prefix_is_preserved(): void
    {
        Persona::for($this->user)->addContact('phone', '+1 (555) 123-4567');

        $this->assertEquals('+15551234567', $this->firstContact()->value);
    }

    public function test_lookup_hash_cast_outputs_hmac_digest_of_normalized_value(): void
    {
        Persona::for($this->user)->addContact('email', 'ALICE@Example.com');

        $contact = $this->firstContact();

        $this->assertEquals(PersonaHasher::hash('alice@example.com'), $contact->value_hash);
        $this->assertNotEquals('alice@example.com', $contact->value_hash);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $contact->value_hash);
    }

    public function test_raw_hashed_value_is_never_stored_in_cleartext_hash_column(): void
    {
        Persona::for($this->user)->addContact('email', 'alice@example.com');

        $raw = DB::table(config('persona.tables.contacts'))
            ->where('personable_type', $this->user->getMorphClass())
            ->where('personable_id', $this->user->getKey())
            ->first();

        $this->assertNotEquals('alice@example.com', $raw->value_hash);
        $this->assertEquals(PersonaHasher::hash('alice@example.com'), $raw->value_hash);
    }

    public function test_different_emails_produce_distinct_lookup_hashes(): void
    {
        Persona::for($this->user)->addContact('email', 'alice@example.com');
        Persona::for($this->user)->addContact('email', 'bob@example.com');

        $alice = $this->contactByHash(PersonaHasher::hash('alice@example.com'));
        $bob = $this->contactByHash(PersonaHasher::hash('bob@example.com'));

        $this->assertNotNull($alice);
        $this->assertNotNull($bob);
        $this->assertNotEquals($alice->value_hash, $bob->value_hash);
    }

    public function test_normalization_collision_is_rejected_as_duplicate(): void
    {
        Persona::for($this->user)->addContact('email', 'ALICE@Example.COM');

        $this->expectException(\InvalidArgumentException::class);

        Persona::for($this->user)->addContact('email', ' alice@example.com ');
    }

    public function test_handle_normalizer_canonicalizes_via_container_binding(): void
    {
        Persona::for($this->user)->addContact('handle', '  @Alice_  ');

        $this->assertIsString($this->firstContact()->value);
        $this->assertNotSame('  @Alice_  ', $this->firstContact()->value);
    }

    public function test_lookup_hash_cast_accepts_null_without_error(): void
    {
        $cast = new LookupHash();

        $this->assertNull($cast->set(new TestUser(), 'value_hash', null, []));
    }

    public function test_document_number_is_hashed_not_stored_cleartext(): void
    {
        Persona::for($this->user)->addDocument('passport', 'AA123456');

        $raw = DB::table(config('persona.tables.documents'))
            ->where('personable_type', $this->user->getMorphClass())
            ->where('personable_id', $this->user->getKey())
            ->first();

        $this->assertNotEquals('AA123456', $raw->number_hash);
        $this->assertEquals(PersonaHasher::hash('AA123456'), $raw->number_hash);
    }
}