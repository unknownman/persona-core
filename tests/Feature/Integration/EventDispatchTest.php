<?php

namespace Persona\Tests\Feature\Integration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Persona\Contracts\DocumentVerificationProvider;
use Persona\Events\AddressAdded;
use Persona\Events\AddressMadePrimary;
use Persona\Events\AddressRemoved;
use Persona\Events\ContactAdded;
use Persona\Events\ContactMadePrimary;
use Persona\Events\ContactRemoved;
use Persona\Events\ContactVerified;
use Persona\Events\DocumentAdded;
use Persona\Events\DocumentRemoved;
use Persona\Events\DocumentStatusUpdated;
use Persona\Events\DocumentVerificationRequested;
use Persona\Events\LegalDetailUpdated;
use Persona\Events\PersonaDataWiped;
use Persona\Events\PhysicalAttributeUpdated;
use Persona\Events\ProfileUpdated;
use Persona\Events\RelationshipCreated;
use Persona\Events\RelationshipRemoved;
use Persona\Events\SocialAccountConnected;
use Persona\Events\SocialAccountMadePrimary;
use Persona\Events\SocialAccountRemoved;
use Persona\Persona;
use Persona\Tests\TestCase;
use Persona\Tests\TestUser;

class EventDispatchTest extends TestCase
{
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser();
    }

    public function test_contact_added_event_carries_the_created_contact(): void
    {
        Event::fake();

        $contact = Persona::for($this->user)->addContact('email', 'alice@example.com');

        Event::assertDispatched(ContactAdded::class, function (ContactAdded $event) use ($contact) {
            return $event->contact->is($contact)
                && $event->contact->value === 'alice@example.com'
                && $event->contact->personable_id == $this->user->getKey();
        });
        Event::assertDispatchedTimes(ContactAdded::class, 1);
    }

    public function test_contact_verified_event_dispatched_after_otp_validation(): void
    {
        $contact = Persona::for($this->user)->addContact('email', 'alice@example.com');

        Cache::put("persona:otp:{$contact->getKey()}", '123456', now()->addMinutes(10));

        Event::fake();

        $result = Persona::for($this->user)->verifyContact($contact, '123456');

        $this->assertTrue($result);
        Event::assertDispatched(ContactVerified::class, function (ContactVerified $event) use ($contact) {
            return $event->contact->is($contact);
        });
    }

    public function test_address_events_dispatched_for_add_make_primary_and_delete(): void
    {
        Event::fake();

        $address = Persona::for($this->user)->addAddress('home', '123 Main St', isPrimary: true);

        Event::assertDispatched(AddressAdded::class, fn (AddressAdded $event) => $event->address->is($address));

        Persona::for($this->user)->makeAddressPrimary($address);

        Event::assertDispatched(AddressMadePrimary::class, fn (AddressMadePrimary $event) => $event->address->is($address));

        Persona::for($this->user)->deleteAddress($address);

        Event::assertDispatched(AddressRemoved::class, fn (AddressRemoved $event) => $event->address->is($address));
    }

    public function test_social_account_events_dispatched_for_connect_and_remove(): void
    {
        Event::fake();

        $account = Persona::for($this->user)->addSocialAccount('github', 'alice-gh', isPrimary: true);

        Event::assertDispatched(SocialAccountConnected::class, fn (SocialAccountConnected $event) => $event->account->is($account));

        Persona::for($this->user)->makeSocialAccountPrimary($account);

        Event::assertDispatched(SocialAccountMadePrimary::class, fn (SocialAccountMadePrimary $event) => $event->account->is($account));

        Persona::for($this->user)->deleteSocialAccount($account);

        Event::assertDispatched(SocialAccountRemoved::class, fn (SocialAccountRemoved $event) => $event->account->is($account));
    }

    public function test_relationship_created_event_carries_source_and_target(): void
    {
        Event::fake();

        $target = $this->createUser(id: 2);

        $relationship = Persona::for($this->user)->linkTo($target, 'friend');

        Event::assertDispatched(RelationshipCreated::class, function (RelationshipCreated $event) use ($target, $relationship) {
            return $event->relationship->is($relationship)
                && $event->source->is($this->user)
                && $event->target->is($target)
                && $event->relationship->type === 'friend';
        });
    }

    public function test_relationship_removed_event_dispatched_on_unlink(): void
    {
        Event::fake();

        $target = $this->createUser(id: 2);
        Persona::for($this->user)->linkTo($target, 'friend');

        $unlinked = Persona::for($this->user)->unlinkFrom($target, 'friend');

        $this->assertTrue($unlinked);
        Event::assertDispatched(RelationshipRemoved::class, function (RelationshipRemoved $event) use ($target) {
            return $event->type === 'friend'
                && $event->source->is($this->user)
                && $event->target->is($target);
        });
    }

    public function test_profile_updated_event_carries_the_profile(): void
    {
        Event::fake();

        $profile = Persona::for($this->user)->updateProfile(['first_name' => 'Alice']);

        Event::assertDispatched(ProfileUpdated::class, function (ProfileUpdated $event) use ($profile) {
            return $event->profile->is($profile)
                && $event->profile->first_name === 'Alice';
        });
    }

    public function test_document_added_event_carries_the_document(): void
    {
        Event::fake();

        $document = Persona::for($this->user)->addDocument('passport', 'AA123456');

        Event::assertDispatched(DocumentAdded::class, function (DocumentAdded $event) use ($document) {
            return $event->document->is($document)
                && $event->document->number === 'AA123456';
        });
    }

    public function test_document_status_updated_event_carries_old_and_new_status(): void
    {
        Event::fake();
        Notification::fake();

        $document = Persona::for($this->user)->addDocument('passport', 'AA123456');

        Persona::documents()->updateStatus($document, 'verified');

        Event::assertDispatched(DocumentStatusUpdated::class, function (DocumentStatusUpdated $event) use ($document) {
            return $event->document->is($document)
                && $event->oldStatus === 'pending'
                && $event->newStatus === 'verified';
        });
    }

    public function test_contact_events_dispatched_for_add_make_primary_and_delete(): void
    {
        Event::fake();

        $contact = Persona::for($this->user)->addContact('email', 'bob@example.com');

        Event::assertDispatched(ContactAdded::class, fn (ContactAdded $event) => $event->contact->is($contact));

        Persona::for($this->user)->makeContactPrimary($contact);

        Event::assertDispatched(ContactMadePrimary::class, fn (ContactMadePrimary $event) => $event->contact->is($contact));

        Persona::for($this->user)->deleteContact($contact);

        Event::assertDispatched(ContactRemoved::class, function (ContactRemoved $event) use ($contact) {
            return $event->contact->is($contact);
        });
    }

    public function test_document_removed_event_dispatched_on_delete(): void
    {
        Event::fake();

        $document = Persona::for($this->user)->addDocument('passport', 'AA123456');

        $deleted = Persona::for($this->user)->deleteDocument($document);

        $this->assertTrue($deleted);
        Event::assertDispatched(DocumentRemoved::class, fn (DocumentRemoved $event) => $event->document->is($document));
    }

    public function test_document_verification_requested_dispatched_before_status_change(): void
    {
        Notification::fake();

        $this->app->instance(DocumentVerificationProvider::class, new class implements DocumentVerificationProvider {
            public function verify(Model $document): bool
            {
                return true;
            }
        });

        $order = [];
        Event::listen(DocumentVerificationRequested::class, function (DocumentVerificationRequested $event) use (&$order) {
            $order[] = ['requested', $event->newStatus];
        });
        Event::listen(DocumentStatusUpdated::class, function (DocumentStatusUpdated $event) use (&$order) {
            $order[] = ['status_updated', $event->newStatus];
        });

        $document = Persona::for($this->user)->addDocument('passport', 'AA123456');

        Persona::for($this->user)->root()->documents()->verify($document);

        $this->assertSame([
            ['requested', 'verified'],
            ['status_updated', 'verified'],
        ], $order);
    }

    public function test_physical_attribute_and_legal_detail_updated_events_dispatched(): void
    {
        Event::fake();

        $physical = Persona::for($this->user)->updatePhysicalAttributes(['height' => 170]);
        $legal = Persona::for($this->user)->updateLegalDetails(['nationality' => 'US']);

        Event::assertDispatched(PhysicalAttributeUpdated::class, fn (PhysicalAttributeUpdated $event) => $event->physicalAttribute->is($physical));
        Event::assertDispatched(LegalDetailUpdated::class, fn (LegalDetailUpdated $event) => $event->legalDetail->is($legal));
    }

    public function test_persona_data_wiped_event_dispatched_with_the_entity(): void
    {
        Event::fake();

        Persona::for($this->user)->addContact('email', 'alice@example.com');
        Persona::for($this->user)->forgetAll();

        Event::assertDispatched(PersonaDataWiped::class, fn (PersonaDataWiped $event) => $event->personable->is($this->user));
    }

    public function test_forgetAll_with_no_data_still_dispatches_wiped_event(): void
    {
        Event::fake();

        Persona::for($this->user)->forgetAll();

        Event::assertDispatched(PersonaDataWiped::class, fn (PersonaDataWiped $event) => $event->personable->is($this->user));
    }
}