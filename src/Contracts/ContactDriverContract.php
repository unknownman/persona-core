<?php

namespace Persona\Contracts;

use Illuminate\Database\Eloquent\Model;
use Persona\Models\Contact;

interface ContactDriverContract
{
    public function normalize(string $value): string;
    public function sendVerification(Model $personable, Contact $contact): string;
    public function verify(Model $personable, Contact $contact, string $otp): bool;
}
