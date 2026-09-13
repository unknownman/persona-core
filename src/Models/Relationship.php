<?php

namespace Persona\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Relationship extends Model
{
    protected $guarded = [];

    /**
     * Resolve the table name from the package configuration.
     */
    public function getTable(): string
    {
        return config('persona.tables.relationships', 'persona_relationships');
    }

    public function personable(): MorphTo
    {
        return $this->morphTo();
    }

    public function relatedPersonable(): MorphTo
    {
        return $this->morphTo('related_personable', 'related_personable_type', 'related_personable_id');
    }

    /**
     * Scope: rows where the given model appears on EITHER side of the relationship.
     *
     * Using a single grouped OR keeps this scope composable — any outer
     * `->where(...)` added after the scope will AND-wrap it correctly:
     *
     *   Relationship::forEntity($user)->where('type', 'friend')->get();
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model    $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForEntity(Builder $query, Model $model): Builder
    {
        $type = $model->getMorphClass();
        $id   = (string) $model->getKey();

        return $query->where(function (Builder $q) use ($type, $id): void {
            $q->where(function (Builder $inner) use ($type, $id): void {
                $inner->where('personable_type', $type)
                      ->where('personable_id', $id);
            })->orWhere(function (Builder $inner) use ($type, $id): void {
                $inner->where('related_personable_type', $type)
                      ->where('related_personable_id', $id);
            });
        });
    }
}