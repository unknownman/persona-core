<?php

namespace Persona\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Persona\Managers\ScopedPersonaManager;
use Persona\Models\Address;
use Persona\Models\Contact;
use Persona\Models\Document;
use Persona\Models\LegalDetail;
use Persona\Models\PhysicalAttribute;
use Persona\Models\Profile;
use Persona\Models\Relationship;
use Persona\Models\SocialAccount;
use Persona\Persona;

trait HasPersona
{
    /**
     * Get all persona profiles for the model.
     */
    public function profiles(): MorphMany
    {
        return $this->morphMany(
            config('persona.models.profile', Profile::class),
            'personable'
        );
    }

    /**
     * Get the primary/default persona profile for the model.
     */
    public function profile(): MorphOne
    {
        return $this->morphOne(
            config('persona.models.profile', Profile::class),
            'personable'
        );
    }

    /**
     * Get all persona contacts for the model.
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(
            config('persona.models.contact', Contact::class),
            'personable'
        );
    }

    /**
     * Get all persona addresses for the model.
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(
            config('persona.models.address', Address::class),
            'personable'
        );
    }

    /**
     * Get all persona documents for the model.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(
            config('persona.models.document', Document::class),
            'personable'
        );
    }

    /**
     * Get all persona social accounts for the model.
     */
    public function socialAccounts(): MorphMany
    {
        return $this->morphMany(
            config('persona.models.social_account', SocialAccount::class),
            'personable'
        );
    }

    /**
     * Get directed (outgoing) relationships where this model is the source.
     *
     * Use this for eager-loading or when directionality matters (e.g. 'employer'
     * relationships).  For symmetric types use {@see allRelationships()}.
     */
    public function relationships(): MorphMany
    {
        return $this->morphMany(
            config('persona.models.relationship', Relationship::class),
            'personable'
        );
    }

    /**
     * Query ALL relationships where this model appears on EITHER side.
     *
     * Returns an Eloquent Builder so you can chain additional constraints:
     *
     *   $user->allRelationships()->where('type', 'friend')->get();
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function allRelationships(): Builder
    {
        /** @var \Illuminate\Database\Eloquent\Model $this */
        return Relationship::forEntity($this);
    }

    /**
     * Retrieve the models on the OPPOSITE side of all relationships of the
     * given type involving this model.
     *
     * For each relationship row the method resolves whichever side is NOT
     * this model, eager-loads it, and returns a flat Collection:
     *
     *   $user->relatedEntities('friend');  // → Collection of User models
     *
     * @param  string  $type  Relationship type, e.g. 'friend', 'spouse'.
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function relatedEntities(string $type): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Model $this */
        $selfType = $this->getMorphClass();
        $selfId   = (string) $this->getKey();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Relationship> $rows */
        $rows = Relationship::forEntity($this)
            ->where('type', $type)
            ->with(['personable', 'relatedPersonable'])
            ->get();

        return $rows->map(function (Relationship $row) use ($selfType, $selfId) {
            $isSelf = $row->personable_type === $selfType
                   && (string) $row->personable_id === $selfId;

            return $isSelf ? $row->relatedPersonable : $row->personable;
        })->filter()->values();
    }

    /**
     * Get all persona physical attributes for the model.
     */
    public function physicalAttributes(): MorphMany
    {
        return $this->morphMany(
            config('persona.models.physical_attribute', PhysicalAttribute::class),
            'personable'
        );
    }

    /**
     * Get the single persona physical attributes record for the model.
     */
    public function physicalAttribute(): MorphOne
    {
        return $this->morphOne(
            config('persona.models.physical_attribute', PhysicalAttribute::class),
            'personable'
        );
    }

    /**
     * Get all persona legal details for the model.
     */
    public function legalDetails(): MorphMany
    {
        return $this->morphMany(
            config('persona.models.legal_detail', LegalDetail::class),
            'personable'
        );
    }

    /**
     * Get the single persona legal detail record for the model.
     */
    public function legalDetail(): MorphOne
    {
        return $this->morphOne(
            config('persona.models.legal_detail', LegalDetail::class),
            'personable'
        );
    }

    /**
     * Get the Persona manager scoped to this model.
     */
    public function persona(): ScopedPersonaManager
    {
        return Persona::for($this);
    }
}
