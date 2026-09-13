<?php

namespace Persona\Managers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Persona\Events\RelationshipCreated;
use Persona\Models\Relationship;

class RelationshipManager
{
    /**
     * Link two personable models with a relationship type.
     *
     * Only types present in the configured vocabulary
     * (`persona.relationships.directed` / `persona.relationships.symmetric`)
     * may be persisted. Symmetric types (spouse, sibling, friend, ...) are
     * stored in a canonical order so that A-friend-B and B-friend-A collapse
     * into a single, deduplicated record instead of violating the unique index.
     *
     * Notifications are NOT dispatched here. Listen for the
     * `RelationshipCreated` event (which is `ShouldDispatchAfterCommit`) in
     * the host application to send post-commit notifications — dispatching
     * them inside the transaction risks the queue worker reading dirty data
     * before the commit.
     *
     * @throws \InvalidArgumentException  When the type is not configured, or when linking an entity to itself.
     */
    public function link(Model $source, Model $target, string $type): Relationship
    {
        $this->assertAllowedType($type);

        if ($source->is($target) || (
            $source->getMorphClass() === $target->getMorphClass()
            && (string) $source->getKey() === (string) $target->getKey()
        )) {
            throw new \InvalidArgumentException('A personable entity cannot be linked to itself.');
        }

        [$personable, $relatedPersonable] = $this->canonicalize($source, $target, $type);

        return DB::transaction(function () use ($personable, $relatedPersonable, $source, $target, $type) {
            $existing = Relationship::query()
                ->where('personable_type', $personable->getMorphClass())
                ->where('personable_id', $personable->getKey())
                ->where('related_personable_type', $relatedPersonable->getMorphClass())
                ->where('related_personable_id', $relatedPersonable->getKey())
                ->where('type', $type)
                ->first();

            if ($existing) {
                return $existing;
            }

            $relationship = new Relationship(['type' => $type]);

            $relationship->personable_type = $personable->getMorphClass();
            $relationship->personable_id = $personable->getKey();
            $relationship->related_personable_type = $relatedPersonable->getMorphClass();
            $relationship->related_personable_id = $relatedPersonable->getKey();

            $relationship->save();

            // Dispatched inside the transaction, but `ShouldDispatchAfterCommit`
            // defers the actual dispatch until the transaction commits.
            RelationshipCreated::dispatch($relationship, $source, $target);

            return $relationship;
        });
    }

    /**
     * Assert that the relationship type is part of the configured vocabulary.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertAllowedType(string $type): void
    {
        $directed = config('persona.relationships.directed', []);
        $symmetric = config('persona.relationships.symmetric', []);

        if (! in_array($type, $directed, true) && ! in_array($type, $symmetric, true)) {
            throw new \InvalidArgumentException(
                "The relationship type '{$type}' is not allowed by the persona configuration."
            );
        }
    }

    /**
     * Retrieve all Relationship rows involving the given model (on either side).
     *
     * Optionally filter by relationship type:
     *
     *   $manager->getFor($user, 'friend');
     *
     * @param  \Illuminate\Database\Eloquent\Model  $personable
     * @param  string|null                          $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFor(Model $personable, ?string $type = null): Collection
    {
        $query = Relationship::forEntity($personable);

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    /**
     * Delete the relationship between two models.
     *
     * Accepts arguments in any order — the same canonical-ordering logic
     * used during `link()` is applied here so the record is always found.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $source
     * @param  \Illuminate\Database\Eloquent\Model  $target
     * @param  string                               $type
     * @return bool  `true` when a row was found and deleted, `false` otherwise.
     */
    public function unlink(Model $source, Model $target, string $type): bool
    {
        [$personable, $relatedPersonable] = $this->canonicalize($source, $target, $type);

        $deleted = Relationship::query()
            ->where('personable_type', $personable->getMorphClass())
            ->where('personable_id', $personable->getKey())
            ->where('related_personable_type', $relatedPersonable->getMorphClass())
            ->where('related_personable_id', $relatedPersonable->getKey())
            ->where('type', $type)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Order the two sides canonically for symmetric relation types.
     *
     * The side with the lexicographically smaller morph-class + key
     * signature becomes "personable"; the other becomes
     * "related_personable". Directed types keep their original order.
     *
     * @return array{0: Model, 1: Model}
     */
    protected function canonicalize(Model $source, Model $target, string $type): array
    {
        $symmetric = config('persona.relationships.symmetric', []);

        if (! in_array($type, $symmetric, true)) {
            return [$source, $target];
        }

        $sourceSignature = $source->getMorphClass() . ':' . $source->getKey();
        $targetSignature = $target->getMorphClass() . ':' . $target->getKey();

        if (strcmp($sourceSignature, $targetSignature) <= 0) {
            return [$source, $target];
        }

        return [$target, $source];
    }
}