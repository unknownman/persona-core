<?php

namespace Persona\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Persona\Managers\PersonaManager;

class CleanOrphansCommand extends Command
{
    protected $signature = 'persona:clean-orphans
                            {--pretend : Display orphans that would be removed without deleting anything}
                            {--chunk= : Number of orphaned rows to delete per batch (default: persona.cleanup.chunk_size)}';

    protected $description = 'Delete Persona rows whose polymorphic parent no longer exists.';

    public function handle(): int
    {
        $tables = (array) config('persona.tables', []);

        $total = 0;

        foreach (PersonaManager::PERSONABLE_TABLES as $key) {
            if (! isset($tables[$key])) {
                continue;
            }

            $removed = $this->sweep($tables[$key], 'personable');

            $this->components->twoColumnDetail($tables[$key], "{$this->verb()} {$removed} orphan(s)");

            $total += $removed;
        }

        if (isset($tables['relationships'])) {
            $removed = $this->sweep($tables['relationships'], 'personable')
                + $this->sweep($tables['relationships'], 'related_personable');

            $this->components->twoColumnDetail($tables['relationships'], "{$this->verb()} {$removed} orphan(s)");

            $total += $removed;
        }

        $verb = $this->option('pretend') ? 'found' : 'removed';

        $this->components->info("Persona cleanup complete: {$total} orphaned record(s) {$verb}.");

        return self::SUCCESS;
    }

    /**
     * Sweep a single persona table for rows whose polymorphic parent is gone.
     */
    protected function sweep(string $personaTable, string $morphPrefix): int
    {
        $morphType = "{$morphPrefix}_type";
        $morphId = "{$morphPrefix}_id";

        if (! Schema::hasColumns($personaTable, [$morphType, $morphId])) {
            return 0;
        }

        $types = DB::table($personaTable)
            ->distinct()
            ->pluck($morphType)
            ->filter();

        $removed = 0;

        foreach ($types as $type) {
            $parent = $this->resolveParent($type);

            if ($parent === null) {
                $this->components->warn(sprintf(
                    'Skipping unmapped morph type [%s] found on [%s].',
                    $type,
                    $personaTable,
                ));

                continue;
            }

            if ($this->preservesPersonaOnDelete($parent)) {
                $this->components->info(sprintf(
                    'Skipping [%s] (Preservation enabled).',
                    class_basename($parent),
                ));

                continue;
            }

            $removed += $this->sweepType($parent, $personaTable, $morphType, $morphId);
        }

        return $removed;
    }

    /**
     * Remove rows of a single morph type that point at a missing parent.
     *
     * Orphaned rows are collected as ascending IDs and deleted in batches so
     * very large tables never lock for extended periods or exhaust memory.
     */
    protected function sweepType(
        Model $parent,
        string $personaTable,
        string $morphType,
        string $morphId,
    ): int {
        $parentTable = $parent->getTable();
        $parentKey = $parent->getKeyName();

        $orphans = DB::table($personaTable)
            ->select("{$personaTable}.id")
            ->where($morphType, $parent->getMorphClass())
            ->whereNotExists(function (Builder $query) use ($personaTable, $morphId, $parentTable, $parentKey) {
                $query->selectRaw('1')
                    ->from($parentTable)
                    ->whereColumn("{$parentTable}.{$parentKey}", "{$personaTable}.{$morphId}");
            });

        if ($this->option('pretend')) {
            return $orphans->count();
        }

        return $this->deleteOrphansInChunks($orphans, $personaTable);
    }

    /**
     * Delete orphaned rows in ascending-ID batches to avoid long table locks.
     */
    protected function deleteOrphansInChunks(Builder $orphans, string $personaTable): int
    {
        $removed = 0;

        $orphans->chunkById($this->chunkSize(), function ($rows) use ($personaTable, &$removed): void {
            $removed += DB::table($personaTable)
                ->whereIn('id', $rows->pluck('id')->all())
                ->delete();
        });

        return $removed;
    }

    /**
     * The number of orphaned rows to delete per batch.
     */
    protected function chunkSize(): int
    {
        return max(1, (int) ($this->option('chunk') ?: config('persona.cleanup.chunk_size', 1000)));
    }

    /**
     * Resolve a stored morph type to a usable parent model, or null.
     */
    protected function resolveParent(string $type): ?Model
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        return new $class();
    }

    /**
     * Whether the host model opts out of automatic Persona deletion.
     */
    protected function preservesPersonaOnDelete(Model $parent): bool
    {
        return (property_exists($parent, 'preservePersonaOnDelete') && $parent->preservePersonaOnDelete)
            || (method_exists($parent, 'preservePersonaOnDelete') && $parent->preservePersonaOnDelete());
    }

    protected function verb(): string
    {
        return $this->option('pretend') ? 'would remove' : 'removed';
    }
}