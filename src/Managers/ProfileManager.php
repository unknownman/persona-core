<?php

namespace Persona\Managers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Persona\Events\ProfileUpdated;
use Persona\Models\Profile;

class ProfileManager
{
    /**
     * Update the existing Profile for the given personable model, or create
     * one when none exists yet. There is exactly one Profile per entity.
     *
     * Runs inside a DB transaction and dispatches ProfileUpdated (which is
     * ShouldDispatchAfterCommit) once the row is persisted.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreate(Model $personable, array $attributes): Profile
    {
        $properties = $this->map($attributes);

        return DB::transaction(function () use ($personable, $properties) {
            $profile = Profile::query()
                ->where('personable_type', $personable->getMorphClass())
                ->where('personable_id', $personable->getKey())
                ->first();

            if ($profile === null) {
                $profile = new Profile($properties);

                $profile->personable_type = $personable->getMorphClass();
                $profile->personable_id = $personable->getKey();
            } else {
                $profile->fill($properties);
            }

            $profile->save();

            ProfileUpdated::dispatch($profile);

            return $profile;
        });
    }

    /**
     * Reduce the incoming attributes to the explicit whitelist, protecting
     * internal columns from raw array mass-assignment.
     *
     * The whitelist lives in `persona.fillable.profile` so the host
     * application can extend it without touching this manager.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function map(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(config('persona.fillable.profile', [])));
    }
}