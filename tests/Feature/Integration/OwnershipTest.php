<?php

namespace Persona\Tests\Feature\Integration;

use Persona\Models\Address;
use Persona\Models\Contact;
use Persona\Persona;
use Persona\Tests\TestCase;
use Persona\Tests\TestUser;

class OwnershipTest extends TestCase
{
    private TestUser $userA;
    private TestUser $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = $this->createUser(id: 1);
        $this->userB = $this->createUser(id: 2);
    }

    private function contactsFor(TestUser $user)
    {
        return Contact::where('personable_type', $user->getMorphClass())
            ->where('personable_id', $user->getKey());
    }

    public function test_contact_makePrimary_throws_for_other_entity(): void
    {
        $contact = Persona::for($this->userA)->addContact('email', 'a@example.com');

        $this->expectException(\InvalidArgumentException::class);

        Persona::for($this->userB)->makeContactPrimary($contact);
    }

    public function test_contact_delete_throws_for_other_entity(): void
    {
        $contact = Persona::for($this->userA)->addContact('email', 'a@example.com');

        $this->expectException(\InvalidArgumentException::class);

        Persona::for($this->userB)->deleteContact($contact);
    }

    public function test_contact_updateValue_throws_for_other_entity(): void
    {
        $contact = Persona::for($this->userA)->addContact('email', 'a@example.com');

        $this->expectException(\InvalidArgumentException::class);

        Persona::for($this->userB)->updateContactValue($contact, 'new@example.com');
    }

    public function test_contact_sendVerification_throws_for_other_entity(): void
    {
        $contact = Persona::for($this->userA)->addContact('email', 'a@example.com');

        $this->expectException(\InvalidArgumentException::class);

        Persona::for($this->userB)->sendContactVerification($contact);
    }

    public function test_contact_verify_throws_for_other_entity(): void
    {
        $contact = Persona::for($this->userA)->addContact('email', 'a@example.com');

        $this->expectException(\InvalidArgumentException::class);

        Persona::for($this->userB)->verifyContact($contact, '123456');
    }

    public function test_address_makePrimary_throws_for_other_entity(): void
    {
        $address = Persona::for($this->userA)->addAddress('home', '123 Main St');

        $this->expectException(\InvalidArgumentException::class);

        Persona::for($this->userB)->makeAddressPrimary($address);
    }

    public function test_address_delete_throws_for_other_entity(): void
    {
        $address = Persona::for($this->userA)->addAddress('home', '123 Main St');

        $this->expectException(\InvalidArgumentException::class);

        Persona::for($this->userB)->deleteAddress($address);
    }

    public function test_social_account_makePrimary_throws_for_other_entity(): void
    {
        $account = Persona::for($this->userA)->addSocialAccount('github', 'alice-gh');

        $this->expectException(\InvalidArgumentException::class);

        Persona::for($this->userB)->makeSocialAccountPrimary($account);
    }

    public function test_social_account_delete_throws_for_other_entity(): void
    {
        $account = Persona::for($this->userA)->addSocialAccount('github', 'alice-gh');

        $this->expectException(\InvalidArgumentException::class);

        Persona::for($this->userB)->deleteSocialAccount($account);
    }

    public function test_user_a_sees_only_own_contacts(): void
    {
        Persona::for($this->userA)->addContact('email', 'a@example.com');
        Persona::for($this->userB)->addContact('email', 'b@example.com');

        $contacts = $this->contactsFor($this->userA)->get();

        $this->assertCount(1, $contacts);
        $this->assertEquals('a@example.com', $contacts->first()->value);
    }

    public function test_user_b_sees_only_own_addresses(): void
    {
        Persona::for($this->userA)->addAddress('home', 'A St');
        Persona::for($this->userB)->addAddress('home', 'B St');

        $addresses = Address::where('personable_type', $this->userB->getMorphClass())
            ->where('personable_id', $this->userB->getKey())
            ->get();

        $this->assertCount(1, $addresses);
        $this->assertEquals('B St', $addresses->first()->line_1);
    }
}