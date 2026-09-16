<?php

namespace Persona\Tests\Feature\Integration;

use Persona\Models\Address;
use Persona\Models\Contact;
use Persona\Models\SocialAccount;
use Persona\Persona;
use Persona\Support\PersonaHasher;
use Persona\Tests\TestCase;
use Persona\Tests\TestUser;

class PrimaryInvariantTest extends TestCase
{
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser();
    }

    private function contactByValue(string $value): Contact
    {
        return Contact::where('personable_type', $this->user->getMorphClass())
            ->where('personable_id', $this->user->getKey())
            ->where('value_hash', PersonaHasher::hash($value))
            ->firstOrFail();
    }

    private function addressByLine1(string $line1): Address
    {
        return Address::where('personable_type', $this->user->getMorphClass())
            ->where('personable_id', $this->user->getKey())
            ->where('line_1', $line1)
            ->firstOrFail();
    }

    private function accountByUsername(string $username): SocialAccount
    {
        return SocialAccount::where('personable_type', $this->user->getMorphClass())
            ->where('personable_id', $this->user->getKey())
            ->where('username', $username)
            ->firstOrFail();
    }

    public function test_adding_new_primary_contact_demotes_existing(): void
    {
        Persona::for($this->user)->addContact('email', 'old@example.com', isPrimary: true);
        Persona::for($this->user)->addContact('email', 'new@example.com', isPrimary: true);

        $this->assertTrue($this->contactByValue('new@example.com')->is_primary);
        $this->assertFalse($this->contactByValue('old@example.com')->is_primary);
    }

    public function test_makePrimary_demotes_previous_primary(): void
    {
        $old = Persona::for($this->user)->addContact('email', 'old@example.com', isPrimary: true);
        $new = Persona::for($this->user)->addContact('email', 'new@example.com');

        Persona::for($this->user)->makeContactPrimary($new);

        $this->assertTrue($new->fresh()->is_primary);
        $this->assertFalse($old->fresh()->is_primary);
    }

    public function test_adding_primary_address_demotes_previous_primary(): void
    {
        Persona::for($this->user)->addAddress('home', '123 Old St', isPrimary: true);
        Persona::for($this->user)->addAddress('home', '456 New St', isPrimary: true);

        $this->assertTrue($this->addressByLine1('456 New St')->is_primary);
        $this->assertFalse($this->addressByLine1('123 Old St')->is_primary);
    }

    public function test_address_makePrimary_demotes_previous_primary(): void
    {
        $old = Persona::for($this->user)->addAddress('home', '123 Old St', isPrimary: true);
        $new = Persona::for($this->user)->addAddress('home', '456 New St');

        Persona::for($this->user)->makeAddressPrimary($new);

        $this->assertTrue($new->fresh()->is_primary);
        $this->assertFalse($old->fresh()->is_primary);
    }

    public function test_social_account_single_primary_per_platform(): void
    {
        Persona::for($this->user)->addSocialAccount('github', 'old-gh', isPrimary: true);
        Persona::for($this->user)->addSocialAccount('github', 'new-gh', isPrimary: true);

        $this->assertTrue($this->accountByUsername('new-gh')->is_primary);
        $this->assertFalse($this->accountByUsername('old-gh')->is_primary);
    }

    public function test_social_account_makePrimary_demotes_previous_primary(): void
    {
        $old = Persona::for($this->user)->addSocialAccount('github', 'old-gh', isPrimary: true);
        $new = Persona::for($this->user)->addSocialAccount('github', 'new-gh');

        Persona::for($this->user)->makeSocialAccountPrimary($new);

        $this->assertTrue($new->fresh()->is_primary);
        $this->assertFalse($old->fresh()->is_primary);
    }

    public function test_different_contact_types_have_independent_primary(): void
    {
        $email = Persona::for($this->user)->addContact('email', 'alice@example.com', isPrimary: true);
        $phone = Persona::for($this->user)->addContact('phone', '+15551234567', isPrimary: true);

        $this->assertTrue($email->fresh()->is_primary);
        $this->assertTrue($phone->fresh()->is_primary);
    }

    public function test_different_address_types_have_independent_primary(): void
    {
        $home = Persona::for($this->user)->addAddress('home', '123 Home St', isPrimary: true);
        $work = Persona::for($this->user)->addAddress('work', '456 Work St', isPrimary: true);

        $this->assertTrue($home->fresh()->is_primary);
        $this->assertTrue($work->fresh()->is_primary);
    }
}