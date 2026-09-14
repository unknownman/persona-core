<?php

namespace Persona\Tests\Feature\Managers;

use Illuminate\Support\Facades\Event;
use Persona\Events\AddressAdded;
use Persona\Events\AddressMadePrimary;
use Persona\Events\AddressRemoved;
use Persona\Persona;
use Persona\Tests\TestCase;

class AddressManagerTest extends TestCase
{
    public function test_add_address_dispatches_address_added_once(): void
    {
        Event::fake();

        $user = $this->createUser();
        $address = Persona::for($user)->addAddress('home', '1 Main St', ['city' => 'Berlin']);

        Event::assertDispatchedTimes(AddressAdded::class, 1);
        Event::assertDispatched(AddressAdded::class, function (AddressAdded $event) use ($address, $user) {
            return $event->address->is($address)
                && $event->address->city === 'Berlin'
                && $event->address->personable_id == $user->getKey();
        });
    }

    public function test_make_primary_dispatches_address_made_primary_once(): void
    {
        Event::fake();

        $user = $this->createUser();
        $address = Persona::for($user)->addAddress('home', '1 Main St');

        Persona::for($user)->makeAddressPrimary($address);

        Event::assertDispatchedTimes(AddressMadePrimary::class, 1);
        Event::assertDispatched(AddressMadePrimary::class, function (AddressMadePrimary $event) use ($address) {
            return $event->address->is($address) && $event->address->is_primary === true;
        });
    }

    public function test_delete_address_dispatches_address_removed_once(): void
    {
        Event::fake();

        $user = $this->createUser();
        $address = Persona::for($user)->addAddress('home', '1 Main St');

        $deleted = Persona::for($user)->deleteAddress($address);

        $this->assertTrue($deleted);
        Event::assertDispatchedTimes(AddressRemoved::class, 1);
        Event::assertDispatched(AddressRemoved::class, function (AddressRemoved $event) use ($address) {
            return $event->address->is($address);
        });
    }
}