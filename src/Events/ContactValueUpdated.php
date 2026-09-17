<?php

namespace Persona\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Persona\Models\Contact;

class ContactValueUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Contact $contact,
        public string $oldValue,
        public string $newValue,
    ) {}
}