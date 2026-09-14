<?php

namespace Persona\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Persona\Models\Document;

class DocumentVerificationRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Document $document,
        public ?string $newStatus = null,
    ) {}
}