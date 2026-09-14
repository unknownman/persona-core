<?php

namespace Persona\Tests\Feature\Managers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Persona\Models\Profile;
use Persona\Persona;
use Persona\Tests\TestCase;
use Persona\Traits\HasPersona;

class ScopedPersonaManagerTest extends TestCase
{
    public function test_getFootprint_resolves_every_section_for_plain_models(): void
    {
        $user = $this->createUser();

        $contact = Persona::for($user)->addContact('email', 'ali@example.com');
        $address = Persona::for($user)->addAddress('home', '1 Main St');
        Persona::for($user)->updateProfile(['first_name' => 'Ali']);
        Persona::for($user)->updateLegalDetails(['nationality' => 'DE']);

        $footprint = Persona::for($user)->getFootprint();

        $this->assertSame([
            'profile',
            'contacts',
            'addresses',
            'documents',
            'socialAccounts',
            'relationships',
            'physicalAttribute',
            'legalDetail',
        ], array_keys($footprint));

        $this->assertInstanceOf(Profile::class, $footprint['profile']);
        $this->assertSame('Ali', $footprint['profile']->first_name);
        $this->assertTrue($footprint['contacts']->contains($contact));
        $this->assertTrue($footprint['addresses']->contains($address));
        $this->assertInstanceOf(Collection::class, $footprint['documents']);
        $this->assertInstanceOf(Collection::class, $footprint['socialAccounts']);
        $this->assertInstanceOf(Collection::class, $footprint['relationships']);
        $this->assertNull($footprint['physicalAttribute']);
        $this->assertSame('DE', $footprint['legalDetail']->nationality);
    }

    public function test_getFootprint_uses_trait_relations_when_available(): void
    {
        Schema::create('test_trait_users', function ($table) {
            $table->id();
            $table->timestamps();
        });

        $user = TestTraitUser::create(['id' => 7]);
        Persona::for($user)->addContact('email', 'trait@example.com');

        $footprint = $user->persona()->getFootprint();

        $this->assertCount(1, $footprint['contacts']);
        $this->assertSame('trait@example.com', $footprint['contacts']->first()->value);
        $this->assertInstanceOf(Collection::class, $footprint['relationships']);
    }
}

class TestTraitUser extends Model
{
    use HasPersona;

    protected $guarded = [];

    protected $table = 'test_trait_users';
}