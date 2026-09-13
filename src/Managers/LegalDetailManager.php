<?php

namespace Persona\Managers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Persona\Models\LegalDetail;

class LegalDetailManager
{
    /**
     * The only columns a host application may write through this manager.
     *
     * `tax_id_hash` is deliberately absent: it is a LookupHash derived from
     * `tax_id` and is always recomputed by this manager so hosts can never
     * plant an arbitrary hash.
     */
    protected const FILLABLE_PROPERTIES = [
        'nationality',
        'marital_status',
        'tax_id',
    ];

    /**
     * Update the existing LegalDetail record for the given personable model,
     * or create one when none exists yet. There is exactly one such record
     * per entity, so this acts as the sole mutation entry point.
     *
     * When a `tax_id` is supplied the lookup hash is derived automatically;
     * when it is nulled the hash is cleared as well. Runs in a transaction.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreate(Model $personable, array $attributes): LegalDetail
    {
        $properties = $this->map($attributes);

        if (array_key_exists('tax_id', $properties)) {
            $properties['tax_id_hash'] = $properties['tax_id'] === null || $properties['tax_id'] === ''
                ? null
                : $properties['tax_id'];
        }

        return DB::transaction(function () use ($personable, $properties) {
            $legalDetail = LegalDetail::query()
                ->where('personable_type', $personable->getMorphClass())
                ->where('personable_id', $personable->getKey())
                ->first();

            if ($legalDetail === null) {
                $legalDetail = new LegalDetail($properties);

                $legalDetail->personable_type = $personable->getMorphClass();
                $legalDetail->personable_id = $personable->getKey();
            } else {
                $legalDetail->fill($properties);
            }

            $legalDetail->save();

            return $legalDetail;
        });
    }

    /**
     * Reduce the incoming attributes to the explicit whitelist, protecting
     * internal columns from raw array mass-assignment.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function map(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(self::FILLABLE_PROPERTIES));
    }
}