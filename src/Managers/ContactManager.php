<?php

namespace Persona\Managers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Persona\Events\ContactAdded;
use Persona\Events\ContactMadePrimary;
use Persona\Events\ContactRemoved;
use Persona\Events\ContactValueUpdated;
use Persona\Events\ContactVerified;
use Persona\Models\Contact;
use Persona\Notifications\VerifyContactNotification;
use Persona\Persona;
use Persona\Support\PersonaHasher;
use Persona\Support\PersonaNormalizer;

class ContactManager
{
    public function __construct(
        protected ContactDriverManager $driverManager
    ) {}

    /**
     * Add a contact value for a personable model.
     *
     * Raw string values are passed to the model; the encryption and
     * hashing casts take care of storage and unique lookups.
     *
     * @param  bool  $isPrimary  When true, this contact becomes the single
     *                           primary contact for the given type.
     * @param  bool  $isEmergency  Marks the contact as an emergency line.
     *
     * @throws \InvalidArgumentException  When an active contact with the same value already exists for the entity, or when the type is not allowed.
     */
    public function add(
        Model $personable,
        string $type,
        string $value,
        bool $isPrimary = false,
        bool $isEmergency = false,
    ): Contact {
        $this->assertAllowedType($type);

        $driver = $this->driverManager->driver($type);
        $value = $driver->normalize($value);

        return DB::transaction(function () use ($personable, $type, $value, $isPrimary, $isEmergency) {
            // The unique index is (personable_type, personable_id, type,
            // value_hash) and it covers soft-deleted rows too. A previously
            // trashed twin is matched via withTrashed() and restored instead
            // of triggering an SQL integrity violation on insert.
            $existing = Contact::withTrashed()
                ->where('personable_type', $personable->getMorphClass())
                ->where('personable_id', $personable->getKey())
                ->where('type', $type)
                ->where('value_hash', $this->lookupHash($value))
                ->first();

            if ($existing) {
                if (! $existing->trashed()) {
                    throw new \InvalidArgumentException(
                        __('This contact is already registered for this entity.')
                    );
                }

                if ($isPrimary) {
                    $this->demoteOtherContacts($personable, $type);
                }

                $existing->restore();
                $existing->fill([
                    'value' => $value,
                    'value_hash' => $value,
                    'is_primary' => $isPrimary,
                    'is_emergency' => $isEmergency,
                    'is_verified' => false,
                    'verified_at' => null,
                ])->save();

                ContactAdded::dispatch($existing);

                return $existing;
            }

            if ($isPrimary) {
                $this->demoteOtherContacts($personable, $type);
            }

            $contact = new Contact([
                'type' => $type,
                'value' => $value,
                'value_hash' => $value,
                'is_primary' => $isPrimary,
                'is_emergency' => $isEmergency,
            ]);

            $contact->personable_type = $personable->getMorphClass();
            $contact->personable_id = $personable->getKey();
            $contact->save();

            ContactAdded::dispatch($contact);

            return $contact;
        });
    }

    /**
     * Demote every other active contact of the same type for the personable,
     * enforcing the single-primary invariant before a new primary is set.
     */
    protected function demoteOtherContacts(Model $personable, string $type): void
    {
        Contact::query()
            ->where('personable_type', $personable->getMorphClass())
            ->where('personable_id', $personable->getKey())
            ->where('type', $type)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    /**
     * Assert that the contact type is part of the configured vocabulary.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertAllowedType(string $type): void
    {
        $allowed = config('persona.contact_types', array_keys(config('persona.normalizers', [])));

        if (! in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException(
                __("The contact type '{$type}' is not allowed by the persona configuration.")
            );
        }
    }

    /**
     * Mark a contact as the primary contact for its type and personable model.
     *
     * @throws \InvalidArgumentException  When the contact does not belong to the given entity.
     */
    public function makePrimary(Model $personable, Contact $contact): void
    {
        $this->assertOwnership($personable, $contact);

        DB::transaction(function () use ($personable, $contact) {
            Contact::query()
                ->where('personable_type', $personable->getMorphClass())
                ->where('personable_id', $personable->getKey())
                ->where('type', $contact->type)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            $contact->update(['is_primary' => true]);

            // Dispatched inside the transaction, but `ShouldDispatchAfterCommit`
            // defers the actual dispatch until the transaction commits.
            ContactMadePrimary::dispatch($contact);
        });
    }

    /**
     * Delete a contact, verifying it belongs to the given personable entity first.
     *
     * @throws \InvalidArgumentException  When the contact does not belong to the given entity.
     */
    public function delete(Model $personable, Contact $contact): bool
    {
        $this->assertOwnership($personable, $contact);

        $deleted = DB::transaction(fn (): bool => (bool) $contact->delete());

        if ($deleted) {
            ContactRemoved::dispatch($contact);
        }

        return $deleted;
    }

    /**
     * Update the value of an existing contact.
     *
     * Mirrors `add()`'s safety guarantees: the new value is normalized and
     * deduplicated against every other contact of the same type, a trashed
     * twin is restored (inheriting the current contact's flags) instead of
     * re-inserting, and the change is announced through `ContactValueUpdated`.
     * The verification status is reset since the previously verified value is
     * no longer valid.
     *
     * @throws \InvalidArgumentException  When the contact does not belong to the given entity, or another active contact already holds the value.
     */
    public function updateValue(Model $personable, Contact $contact, string $newValue): Contact
    {
        $this->assertOwnership($personable, $contact);

        $oldValue = (string) $contact->value;
        $driver = $this->driverManager->driver((string) $contact->type);
        $newValue = $driver->normalize($newValue);

        return DB::transaction(function () use ($personable, $contact, $oldValue, $newValue) {
            // A previously trashed twin (same personable, type and value_hash)
            // is matched via withTrashed() and restored — exactly like `add()`
            // — instead of triggering an SQL integrity violation on update.
            $twin = Contact::withTrashed()
                ->where('personable_type', $personable->getMorphClass())
                ->where('personable_id', $personable->getKey())
                ->where('type', $contact->type)
                ->where('value_hash', $this->lookupHash($newValue))
                ->where('id', '!=', $contact->getKey())
                ->first();

            if ($twin) {
                if (! $twin->trashed()) {
                    throw new \InvalidArgumentException(
                        __('This contact is already registered for this entity.')
                    );
                }

                if ($contact->is_primary) {
                    $this->demoteOtherContacts($personable, $contact->type);
                }

                $twin->restore();
                $twin->fill([
                    'value' => $newValue,
                    'value_hash' => $newValue,
                    'is_primary' => $contact->is_primary,
                    'is_emergency' => $contact->is_emergency,
                    'is_verified' => false,
                    'verified_at' => null,
                ])->save();

                $contact->delete();

                ContactValueUpdated::dispatch($twin, $oldValue, $newValue);

                return $twin;
            }

            $contact->update([
                'value' => $newValue,
                'value_hash' => $newValue,
                'is_verified' => false,
                'verified_at' => null,
            ]);

            ContactValueUpdated::dispatch($contact, $oldValue, $newValue);

            return $contact;
        });
    }

    /**
     * Generate an OTP, cache it, and dispatch a verification notification
     * strictly to the contact value being verified (never the owning profile).
     *
     * Email contacts are routed through the mail channel to the email itself.
     * Phone contacts are routed through a host-configured notification channel
     * (see the `persona.otp.sms_channel` config key) so no third-party SMS
     * provider is hardcoded here. Custom notifications supplied via
     * `Persona::verifyContactsUsing()` are routed as-is: the notification's
     * own `via()` method decides the channels, so any standard Notification
     * class can be used without package-specific methods.
     *
     * @throws \InvalidArgumentException  When the contact does not belong to the given entity.
     */
    public function sendVerification(Model $personable, Contact $contact): string
    {
        $this->assertOwnership($personable, $contact);

        return $this->driverManager->driver($contact->type)->sendVerification($personable, $contact);
    }

    /**
     * Verify an OTP and mark the contact as verified.
     *
     * @throws \InvalidArgumentException  When the contact does not belong to the given entity.
     */
    public function verify(Model $personable, Contact $contact, string $otp): bool
    {
        $this->assertOwnership($personable, $contact);

        return $this->driverManager->driver($contact->type)->verify($personable, $contact, $otp);
    }

    /**
     * Compute the lookup hash for a raw contact value.
     *
     * Delegates to PersonaHasher so the deduplication query matches the hash
     * that will actually be persisted by the cast.
     */
    protected function lookupHash(string $value): string
    {
        return PersonaHasher::hash($value);
    }

    /**
     * Assert that a Contact is owned by the given personable entity.
     *
     * This is the single, canonical ownership invariant for the ContactManager.
     * It must be called before ANY mutation that accepts an external Contact
     * instance to prevent cross-entity IDOR attacks.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertOwnership(Model $personable, Contact $contact): void
    {
        if (
            $contact->personable_type !== $personable->getMorphClass()
            || (string) $contact->personable_id !== (string) $personable->getKey()
        ) {
            throw new \InvalidArgumentException(
                __('The given contact does not belong to this entity.')
            );
        }
    }
}