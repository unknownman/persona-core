<?php

namespace Persona\Managers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Persona\Events\AddressAdded;
use Persona\Events\AddressMadePrimary;
use Persona\Events\AddressRemoved;
use Persona\Models\Address;

class AddressManager
{
    /**
     * Add an address for a personable model.
     *
     * When $isPrimary is true the manager demotes every existing address of
     * the same $type to non-primary first, so the single-primary-per-type
     * invariant holds. The whole operation runs in one DB transaction.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function add(
        Model $personable,
        string $type,
        string $line1,
        array $attributes = [],
        bool $isPrimary = false,
    ): Address {
        return DB::transaction(function () use ($personable, $type, $line1, $attributes, $isPrimary) {
            if ($isPrimary) {
                $this->demoteSameType($personable, $type);
            }

            $address = new Address($this->map($attributes));

            $address->type = $type;
            $address->line_1 = $line1;
            $address->is_primary = $isPrimary;
            $address->personable_type = $personable->getMorphClass();
            $address->personable_id = $personable->getKey();
            $address->save();

            // Dispatched inside the transaction, but `ShouldDispatchAfterCommit`
            // defers the actual dispatch until the transaction commits.
            AddressAdded::dispatch($address);

            return $address;
        });
    }

    /**
     * Promote an address to primary for its type, preserving the
     * single-primary-per-type invariant inside a transaction.
     *
     * @throws \InvalidArgumentException  When the address does not belong to the given entity.
     */
    public function makePrimary(Model $personable, Address $address): void
    {
        $this->assertOwnership($personable, $address);

        DB::transaction(function () use ($personable, $address) {
            $this->demoteSameType($personable, $address->type);

            $address->update(['is_primary' => true]);

            AddressMadePrimary::dispatch($address);
        });
    }

    /**
     * Delete an address, verifying it belongs to the given entity first.
     *
     * @throws \InvalidArgumentException  When the address does not belong to the given entity.
     */
    public function delete(Model $personable, Address $address): bool
    {
        $this->assertOwnership($personable, $address);

        $deleted = (bool) $address->delete();

        if ($deleted) {
            AddressRemoved::dispatch($address);
        }

        return $deleted;
    }

    /**
     * Demote every primary address of the given type for the entity.
     */
    protected function demoteSameType(Model $personable, string $type): void
    {
        Address::query()
            ->where('personable_type', $personable->getMorphClass())
            ->where('personable_id', $personable->getKey())
            ->where('type', $type)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    /**
     * Assert that an Address is owned by the given personable entity.
     *
     * Must be called before ANY mutation that accepts an external Address
     * instance to prevent cross-entity IDOR attacks.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertOwnership(Model $personable, Address $address): void
    {
        if (
            $address->personable_type !== $personable->getMorphClass()
            || (string) $address->personable_id !== (string) $personable->getKey()
        ) {
            throw new \InvalidArgumentException(
                'The given address does not belong to this entity.'
            );
        }
    }

    /**
     * Reduce the incoming attributes to the explicit whitelist, protecting
     * internal columns from raw array mass-assignment.
     *
     * The whitelist lives in `persona.fillable.address` so the host
     * application can extend it without touching this manager.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function map(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(config('persona.fillable.address', [])));
    }
}