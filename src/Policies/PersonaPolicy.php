<?php

namespace Persona\Policies;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Persona\Traits\BelongsToPersonable;

class PersonaPolicy
{
    /**
     * Determine whether the user may view raw, unmasked PII.
     *
     * The ability is checked against either the owning personable model
     * (`$user->can('viewSensitive', $user)`) or any Persona row directly
     * (`$user->can('viewSensitive', $contact)`) — Persona rows resolve their
     * polymorphic owner first so ownership is always evaluated against the
     * actual record owner.
     *
     * Ownership is a strict identity match: equivalent morph class AND
     * primary key. This intentionally mirrors how host apps mount Persona
     * rows and prevents a same-id record of a different type (multi-guard
     * apps) from being treated as owned. Laravel 12's base
     * `Illuminate\Foundation\Auth\User` no longer ships an `is()` helper,
     * hence the explicit comparison.
     */
    public function viewSensitive(User $user, Model $personable): bool
    {
        if ($this->isPersonaRow($personable)) {
            $owner = $personable->personable;

            return $owner !== null
                && $this->isSameIdentity($user, $owner);
        }

        return $this->isSameIdentity($user, $personable);
    }

    /**
     * Whether the given model is a Persona row carrying a polymorphic owner.
     */
    private function isPersonaRow(Model $model): bool
    {
        return in_array(BelongsToPersonable::class, class_uses_recursive($model), true);
    }

    /**
     * Strict identity check between an authenticated user and an owner record.
     */
    private function isSameIdentity(User $user, Model $owner): bool
    {
        return $user->getMorphClass() === $owner->getMorphClass()
            && (string) $user->getAuthIdentifier() === (string) $owner->getKey();
    }
}