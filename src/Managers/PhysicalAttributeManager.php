<?php

namespace Persona\Managers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Persona\Models\PhysicalAttribute;

class PhysicalAttributeManager
{
    /**
     * Update the existing PhysicalAttribute record for the given personable
     * model, or create one when none exists yet. There is exactly one such
     * record per entity, so this acts as the sole mutation entry point.
     *
     * Runs inside a DB transaction.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreate(Model $personable, array $attributes): PhysicalAttribute
    {
        $properties = $this->map($attributes);

        return DB::transaction(function () use ($personable, $properties) {
            $physicalAttribute = PhysicalAttribute::query()
                ->where('personable_type', $personable->getMorphClass())
                ->where('personable_id', $personable->getKey())
                ->first();

            if ($physicalAttribute === null) {
                $physicalAttribute = new PhysicalAttribute($properties);

                $physicalAttribute->personable_type = $personable->getMorphClass();
                $physicalAttribute->personable_id = $personable->getKey();
            } else {
                $physicalAttribute->fill($properties);
            }

            $physicalAttribute->save();

            return $physicalAttribute;
        });
    }

    /**
     * Reduce the incoming attributes to the explicit whitelist, protecting
     * internal columns from raw array mass-assignment.
     *
     * The whitelist lives in `persona.fillable.physical_attribute` so the
     * host application can extend it without touching this manager.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function map(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(config('persona.fillable.physical_attribute', [])));
    }
}