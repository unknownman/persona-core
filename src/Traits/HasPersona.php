<?php

namespace Persona\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
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
     * Boot the HasPersona trait.
     *
     * Automatically wipes the complete Persona footprint when the owning model
     * is truly removed from the database — either a hard delete on a model
     * without SoftDeletes, or a forceDelete() on a soft-deleting model —
     * preventing orphaned polymorphic rows that the RDBMS cannot cascade away.
     *
     * Host models may retain their Persona data by declaring a
     * `$preservePersonaOnDelete` property (or a `preservePersonaOnDelete()`
     * method) returning true — useful for audit, compliance, or future
     * reassignment of a deleted employee or customer.
     */
    public static function bootHasPersona(): void
    {
        static::deleting(function (Model $model): void {
            $preserve = (property_exists($model, 'preservePersonaOnDelete') && $model->preservePersonaOnDelete)
                     || (method_exists($model, 'preservePersonaOnDelete') && $model->preservePersonaOnDelete());

            if ($preserve) {
                return;
            }

            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                Persona::forgetAll($model);
            }
        });
    }

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

        $this->eagerLoadCounterpartProfiles($rows);

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
     * Eager-load every Persona section needed to render a full profile.
     *
     * Loads `profile`, `contacts`, `addresses`, `documents.files`,
     * `socialAccounts`, `legalDetail`, and `physicalAttribute` in a single
     * round-trip.  Combine with {@see loadPersonaRelationships()} to also
     * resolve the entity graph on both sides:
     *
     *     $user->loadPersonaDetails();
     *     $user->loadPersonaRelationships(); // → Collection<Relationship>
     *
     * @return static
     */
    public function loadPersonaDetails(): static
    {
        $this->load([
            'profile',
            'contacts',
            'addresses',
            'documents.files',
            'socialAccounts',
            'legalDetail',
            'physicalAttribute',
        ]);

        return $this;
    }

    /**
     * Eager-load `profile` on every polymorphic counterpart in a Relationship
     * collection so Blade can read `$counterpart->profile` without N+1 queries.
     *
     * Works by collecting the distinct morph class strings from both sides and
     * delegating to Laravel's `Collection::loadMorph`.
     */
    private function eagerLoadCounterpartProfiles(Collection $relationships): void
    {
        $morphMap = $relationships->pluck('personable_type')
            ->merge($relationships->pluck('related_personable_type'))
            ->filter()
            ->unique()
            ->mapWithKeys(function (string $type) {
                $class = Relation::getMorphedModel($type) ?? $type;

                return [$class => ['profile']];
            });

        $relationships->loadMorph('personable', $morphMap->all());
        $relationships->loadMorph('relatedPersonable', $morphMap->all());
    }

    /**
     * Load ALL relationships where this model appears on EITHER side.
     *
     * Both ends of each row (personable / relatedPersonable) are eager-loaded,
     * so rendering a profile with N relationships stays N+1-free.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Relationship>
     */
    public function loadPersonaRelationships(): Collection
    {
        $relationships = Relationship::forEntity($this)
            ->with(['personable', 'relatedPersonable'])
            ->get();

        $this->eagerLoadCounterpartProfiles($relationships);

        return $relationships;
    }

    /**
     * Get the Persona manager scoped to this model.
     */
    public function persona(): ScopedPersonaManager
    {
        return Persona::for($this);
    }
}
