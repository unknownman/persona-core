<?php

namespace Persona\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

trait BelongsToPersonable
{
    /**
     * The polymorphic owner this Persona row belongs to.
     */
    public function personable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope the query to rows owned by the given personable model, or by a
     * morph-pair (`personable_type` + `personable_id`) supplied directly.
     *
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     */
    public function scopeForPersonable(Builder $query, Model|string $personable, ?int $id = null): Builder
    {
        if ($personable instanceof Model) {
            return $query->where('personable_type', $personable->getMorphClass())
                ->where('personable_id', $personable->getKey());
        }

        return $query->where('personable_type', $personable)
            ->where('personable_id', $id);
    }
}