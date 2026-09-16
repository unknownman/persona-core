<?php

namespace Persona\Tests\Feature\Integration;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Persona\Models\Address;
use Persona\Models\Contact;
use Persona\Models\Document;
use Persona\Models\Relationship;
use Persona\Models\SocialAccount;
use Persona\Persona;
use Persona\Tests\Company;
use Persona\Tests\TestCase;
use Persona\Tests\TestUser;

class PolymorphismTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_companies', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    private function createCompany(int $id = 1): Company
    {
        return Company::create(['id' => $id]);
    }

    public function test_contacts_attached_to_different_entities_are_isolated(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany(id: 10);

        Persona::for($user)->addContact('email', 'alice@example.com');
        $company->persona()->addContact('email', 'alice@example.com');

        $userContact = Contact::where('personable_type', $user->getMorphClass())
            ->where('personable_id', $user->getKey())
            ->first();
        $companyContact = Contact::where('personable_type', $company->getMorphClass())
            ->where('personable_id', $company->getKey())
            ->first();

        $this->assertNotNull($userContact);
        $this->assertNotNull($companyContact);
        $this->assertEquals('alice@example.com', $userContact->value);
        $this->assertEquals('alice@example.com', $companyContact->value);
        $this->assertNotSame($userContact->getKey(), $companyContact->getKey());
        $this->assertEquals(TestUser::class, $userContact->personable_type);
        $this->assertEquals(Company::class, $companyContact->personable_type);
    }

    public function test_addresses_attached_to_different_entities_are_isolated(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany(id: 20);

        Persona::for($user)->addAddress('home', '123 Main St');
        $company->persona()->addAddress('work', '123 Main St');

        $userAddress = Address::where('personable_type', $user->getMorphClass())->first();
        $companyAddress = Address::where('personable_type', $company->getMorphClass())->first();

        $this->assertEquals('home', $userAddress->type);
        $this->assertEquals('work', $companyAddress->type);
    }

    public function test_documents_attached_to_different_entities_are_isolated(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany(id: 30);

        Persona::for($user)->addDocument('passport', 'AA123456');
        $company->persona()->addDocument('passport', 'BB789012');

        $userDocument = Document::where('personable_type', $user->getMorphClass())->first();
        $companyDocument = Document::where('personable_type', $company->getMorphClass())->first();

        $this->assertEquals('AA123456', $userDocument->number);
        $this->assertEquals('BB789012', $companyDocument->number);
    }

    public function test_social_accounts_attached_to_different_entities_are_isolated(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany(id: 50);

        Persona::for($user)->addSocialAccount('github', 'alice-gh');
        $company->persona()->addSocialAccount('github', 'corp-gh');

        $userAccount = SocialAccount::where('personable_type', $user->getMorphClass())->first();
        $companyAccount = SocialAccount::where('personable_type', $company->getMorphClass())->first();

        $this->assertEquals('alice-gh', $userAccount->username);
        $this->assertEquals('corp-gh', $companyAccount->username);
    }

    public function test_forgetOne_entity_does_not_affect_another(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany(id: 40);

        Persona::for($user)->addContact('email', 'alice@example.com');
        $company->persona()->addContact('email', 'bob@example.com');

        Persona::for($user)->forgetAll();

        $this->assertEquals(0, Contact::where('personable_type', $user->getMorphClass())->count());
        $this->assertEquals(1, Contact::where('personable_type', $company->getMorphClass())->count());
        $this->assertEquals('bob@example.com', Contact::where('personable_type', $company->getMorphClass())->first()->value);
    }

    public function test_relationship_references_are_scoped_to_entity_type(): void
    {
        $user = $this->createUser();
        $companyA = $this->createCompany(id: 60);
        $companyB = $this->createCompany(id: 70);

        Persona::for($user)->linkTo($companyA, 'employee');
        Persona::for($user)->linkTo($companyB, 'employee');

        $this->assertEquals(2, Relationship::forEntity($user)->count());
        $this->assertEquals(1, Relationship::forEntity($companyA)->count());
        $this->assertEquals(1, Relationship::forEntity($companyB)->count());
    }

    public function test_company_with_trait_exposes_all_hasPersona_relations(): void
    {
        $company = $this->createCompany(id: 80);

        $company->persona()->updateProfile(['first_name' => 'Acme']);
        $company->persona()->addContact('email', 'info@acme.com');

        $this->assertNotNull($company->profile);
        $this->assertCount(1, $company->contacts);
        $this->assertEquals('Acme', $company->profile->first_name);
    }
}