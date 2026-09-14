<?php

namespace Persona\Managers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Persona\Events\ContactAdded;
use Persona\Events\ContactVerified;
use Persona\Models\Contact;
use Persona\Notifications\VerifyContactNotification;
use Persona\Support\PersonaHasher;
use Persona\Support\PersonaNormalizer;

class ContactManager
{

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
     * @throws \InvalidArgumentException  When an active contact with the same value already exists for the entity.
     */
    public function add(
        Model $personable,
        string $type,
        string $value,
        bool $isPrimary = false,
        bool $isEmergency = false,
    ): Contact {
        $value = PersonaNormalizer::resolve($type, $value);

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

        return (bool) $contact->delete();
    }

    /**
     * Update the value of an existing contact.
     *
     * Keeps `value_hash` in sync with the normalized `value` so lookup
     * queries and uniqueness rules keep working, and resets the verification
     * status since the previously verified value is no longer valid.
     *
     * @throws \InvalidArgumentException  When the contact does not belong to the given entity.
     */
    public function updateValue(Model $personable, Contact $contact, string $newValue): Contact
    {
        $this->assertOwnership($personable, $contact);

        $newValue = PersonaNormalizer::resolve((string) $contact->type, $newValue);

        DB::transaction(function () use ($contact, $newValue) {
            $contact->update([
                'value' => $newValue,
                'value_hash' => $newValue,
                'is_verified' => false,
                'verified_at' => null,
            ]);
        });

        return $contact;
    }

    /**
     * Generate an OTP, cache it, and dispatch a verification notification
     * strictly to the contact value being verified (never the owning profile).
     *
     * Email contacts are routed through the mail channel to the email itself.
     * Phone contacts are routed through a host-configured notification channel
     * (see the `persona.otp.sms_channel` config key) so no third-party SMS
     * provider is hardcoded here.
     *
     * @throws \InvalidArgumentException  When the contact does not belong to the given entity.
     * @throws \RuntimeException  When no notification route can be determined for the contact type.
     */
    public function sendVerification(Model $personable, Contact $contact): string
    {
        $this->assertOwnership($personable, $contact);

        $length = (int) config('persona.otp.length', 6);
        $otp = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $ttl = (int) config('persona.otp.ttl', 600);

        $contactKey = $contact->getKey();

        Cache::put(
            "persona:otp:{$contactKey}",
            $otp,
            Carbon::now()->addSeconds($ttl)
        );

        // A fresh OTP grants a fresh set of attempts.
        Cache::forget("persona:otp_attempts:{$contactKey}");

        $notification = new VerifyContactNotification($otp);

        if ($contact->type === 'email') {
            Notification::route('mail', $contact->value)->notify($notification);
        } elseif ($contact->type === 'phone') {
            $channel = (string) config('persona.otp.sms_channel', 'vonage');

            if (! $notification->supportsChannel($channel)) {
                throw new \RuntimeException(
                    __('Unable to determine notification route for contact type: ' . $contact->type)
                );
            }

            $notification->channels = [$channel];

            Notification::route($channel, $contact->value)->notify($notification);
        } else {
            throw new \RuntimeException(
                __('Unable to determine notification route for contact type: ' . $contact->type)
            );
        }

        return $otp;
    }

    /**
     * Verify an OTP against the cache and mark the contact as verified.
     *
     * The comparison is timing-safe. Failed attempts are tracked in the cache
     * and the OTP is locked after the `persona.otp.max_attempts` configured
     * number of consecutive failures.
     *
     * @throws \InvalidArgumentException  When the contact does not belong to the given entity.
     */
    public function verify(Model $personable, Contact $contact, string $otp): bool
    {
        $this->assertOwnership($personable, $contact);

        $contactKey = $contact->getKey();
        $key = "persona:otp:{$contactKey}";
        $attemptsKey = "persona:otp_attempts:{$contactKey}";
        $maxAttempts = (int) config('persona.otp.max_attempts', 5);

        if ((int) Cache::get($attemptsKey, 0) > $maxAttempts) {
            Cache::forget($key);

            return false;
        }

        $cachedOtp = Cache::get($key);

        if ($cachedOtp === null || ! hash_equals((string) $cachedOtp, (string) $otp)) {
            if ((int) Cache::increment($attemptsKey) > $maxAttempts) {
                Cache::forget($key);
            }

            return false;
        }

        Cache::forget($key);
        Cache::forget($attemptsKey);

        $contact->update([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);

        ContactVerified::dispatch($contact);

        return true;
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