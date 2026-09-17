<?php

namespace Persona\Tests\Feature\Api;

use Illuminate\Foundation\Auth\User as FoundationUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Persona\Api\Http\Resources\ContactResource;
use Persona\Api\Http\Resources\DocumentResource;
use Persona\Api\Http\Resources\LegalDetailResource;
use Persona\Persona;
use Persona\Policies\PersonaPolicy;
use Persona\Tests\TestCase;

class PiiMaskingTest extends TestCase
{
    private function requestFor(?FoundationUser $user): Request
    {
        $request = Request::create('/api/persona', 'GET');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function contact(string $type, string $value): \Persona\Models\Contact
    {
        return Persona::for($this->owner)->addContact($type, $value);
    }

    private FoundationUser $owner;

    private FoundationUser $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = PolicyTestUser::create(['id' => 1]);
        $this->other = PolicyTestUser::create(['id' => 2]);

        // Mirror what a host does for direct personable checks: the same
        // PersonaPolicy gates the host's own user model.
        Gate::policy(PolicyTestUser::class, PersonaPolicy::class);
    }

    public function test_guest_requests_receive_masked_values(): void
    {
        $contact = $this->contact('email', 'ali@example.com');
        $phone = $this->contact('phone', '+1 555 0100 5678');
        $document = Persona::for($this->owner)->addDocument('passport', 'AB12345678');
        $legal = Persona::for($this->owner)->updateLegalDetails(['tax_id' => 'DE123456789']);

        $guest = $this->requestFor(null);

        $this->assertSame('a***@example.com', ContactResource::make($contact)->toArray($guest)['value']);
        $this->assertSame('****5678', ContactResource::make($phone)->toArray($guest)['value']);
        $this->assertSame('****5678', DocumentResource::make($document)->toArray($guest)['number']);
        $this->assertSame('****6789', LegalDetailResource::make($legal)->toArray($guest)['tax_id']);
    }

    public function test_non_owner_users_receive_masked_values(): void
    {
        $contact = $this->contact('email', 'ali@example.com');

        $this->assertFalse($this->other->can('viewSensitive', $contact));
        $this->assertSame('a***@example.com', ContactResource::make($contact)->toArray($this->requestFor($this->other))['value']);
    }

    public function test_owner_receives_raw_decrypted_values(): void
    {
        $contact = $this->contact('email', 'ali@example.com');
        $phone = $this->contact('phone', '+1 555 0100 5678');
        $document = Persona::for($this->owner)->addDocument('passport', 'AB12345678');
        $legal = Persona::for($this->owner)->updateLegalDetails(['tax_id' => 'DE123456789']);

        $request = $this->requestFor($this->owner);

        $this->assertTrue($this->owner->can('viewSensitive', $contact));

        $this->assertSame('ali@example.com', ContactResource::make($contact)->toArray($request)['value']);
        $this->assertSame('+155501005678', ContactResource::make($phone)->toArray($request)['value']);
        $this->assertSame('AB12345678', DocumentResource::make($document)->toArray($request)['number']);
        $this->assertSame('DE123456789', LegalDetailResource::make($legal)->toArray($request)['tax_id']);
    }

    public function test_policy_compares_identity_strictly(): void
    {
        $this->assertTrue($this->owner->can('viewSensitive', $this->owner));
        $this->assertFalse($this->owner->can('viewSensitive', $this->other));
        $this->assertTrue($this->other->can('viewSensitive', $this->other));
        $this->assertFalse($this->other->can('viewSensitive', $this->owner));
    }
}

class PolicyTestUser extends FoundationUser
{
    protected $table = 'test_users';

    protected $guarded = [];
}