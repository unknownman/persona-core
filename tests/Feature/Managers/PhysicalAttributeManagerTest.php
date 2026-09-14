<?php

namespace Persona\Tests\Feature\Managers;

use Illuminate\Support\Facades\Event;
use Persona\Events\PhysicalAttributeUpdated;
use Persona\Persona;
use Persona\Tests\TestCase;

class PhysicalAttributeManagerTest extends TestCase
{
    public function test_update_or_create_dispatches_physical_attribute_updated_once(): void
    {
        Event::fake();

        $user = $this->createUser();
        $attributes = Persona::for($user)->updatePhysicalAttributes(['height' => 180, 'blood_type' => 'O+']);

        Event::assertDispatchedTimes(PhysicalAttributeUpdated::class, 1);
        Event::assertDispatched(PhysicalAttributeUpdated::class, function (PhysicalAttributeUpdated $event) use ($attributes) {
            return $event->physicalAttribute->is($attributes)
                && $event->physicalAttribute->blood_type === 'O+';
        });
    }

    public function test_update_or_create_dispatches_on_update_too(): void
    {
        Event::fake();

        $user = $this->createUser();

        Persona::for($user)->updatePhysicalAttributes(['height' => 180]);
        Persona::for($user)->updatePhysicalAttributes(['height' => 181]);

        Event::assertDispatchedTimes(PhysicalAttributeUpdated::class, 2);
    }
}