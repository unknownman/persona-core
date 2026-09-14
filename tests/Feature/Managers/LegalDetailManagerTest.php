<?php

namespace Persona\Tests\Feature\Managers;

use Illuminate\Support\Facades\Event;
use Persona\Events\LegalDetailUpdated;
use Persona\Persona;
use Persona\Tests\TestCase;

class LegalDetailManagerTest extends TestCase
{
    public function test_update_or_create_dispatches_legal_detail_updated_once(): void
    {
        Event::fake();

        $user = $this->createUser();
        $legalDetail = Persona::for($user)->updateLegalDetails(['nationality' => 'DE']);

        Event::assertDispatchedTimes(LegalDetailUpdated::class, 1);
        Event::assertDispatched(LegalDetailUpdated::class, function (LegalDetailUpdated $event) use ($legalDetail) {
            return $event->legalDetail->is($legalDetail)
                && $event->legalDetail->nationality === 'DE';
        });
    }

    public function test_update_or_create_dispatches_on_update_too(): void
    {
        Event::fake();

        $user = $this->createUser();

        Persona::for($user)->updateLegalDetails(['nationality' => 'DE']);
        Persona::for($user)->updateLegalDetails(['marital_status' => 'married']);

        Event::assertDispatchedTimes(LegalDetailUpdated::class, 2);
    }
}