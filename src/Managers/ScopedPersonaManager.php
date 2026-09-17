<?php

namespace Persona\Managers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Macroable;
use Persona\Models\Address;
use Persona\Models\Contact;
use Persona\Models\Document;
use Persona\Models\DocumentFile;
use Persona\Models\LegalDetail;
use Persona\Models\PhysicalAttribute;
use Persona\Models\Profile;
use Persona\Models\Relationship;
use Persona\Models\SocialAccount;

/**
 * A strictly-typed, model-scoped facade over the root Managers.
 *
 * Obtain an instance via `Persona::for($model)` or `$model->persona()`.
 * Every method pre-fills the owner model so callers never repeat it:
 *
 *   $user->persona()->addContact('email', 'ali@example.com', isPrimary: true);
 *   $user->persona()->updateProfile(['first_name' => 'Ali']);
 *   $user->persona()->addAddress('home', '1 Main St', isPrimary: true);
 *   $user->persona()->linkTo($company, 'employer');
 */
final class ScopedPersonaManager
{
    use Macroable;

    public function __construct(
        private readonly Model $personable,
        private readonly PersonaManager $root,
    ) {}

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * The model this scope is bound to.
     */
    public function getModel(): Model
    {
        return $this->personable;
    }

    /**
     * Escape-hatch to the root PersonaManager for raw / advanced access.
     */
    public function root(): PersonaManager
    {
        return $this->root;
    }

    // -------------------------------------------------------------------------
    // Footprint aggregation
    // -------------------------------------------------------------------------

    /**
     * Resolve the full Persona footprint for the scoped model.
     *
     * Returns a single, well-typed array with every slice a profile overview
     * needs. Models using the `HasPersona` trait are hydrated through their
     * eager-loaded relations (one round-trip via `loadPersonaDetails()`);
     * plain models fall back to direct morph-pair queries keyed by
     * `personable_type` / `personable_id`, so no trait is required either way.
     *
     * When a profile exists, the `avatar_url` attribute (backed by the
     * `AvatarResolverContract` binding) is appended to the Profile model
     * before it is returned, so it is available through both
     * `$profile->avatar_url` and Eloquent `toArray()` — without being part
     * of the model's default `$appends`, which would trigger the external
     * resolver on every serialization of a profile list.
     *
     * @return array{
     *     profile: \Persona\Models\Profile|null,
     *     contacts: \Illuminate\Database\Eloquent\Collection,
     *     addresses: \Illuminate\Database\Eloquent\Collection,
     *     documents: \Illuminate\Database\Eloquent\Collection,
     *     socialAccounts: \Illuminate\Database\Eloquent\Collection,
     *     relationships: \Illuminate\Database\Eloquent\Collection,
     *     physicalAttribute: \Persona\Models\PhysicalAttribute|null,
     *     legalDetail: \Persona\Models\LegalDetail|null,
     * }
     */
    public function getFootprint(): array
    {
        if (method_exists($this->personable, 'loadPersonaDetails')) {
            $this->personable->loadPersonaDetails();

            return [
                'profile'           => $this->personable->profile?->append('avatar_url'),
                'contacts'          => $this->personable->contacts,
                'addresses'         => $this->personable->addresses,
                'documents'         => $this->personable->documents,
                'socialAccounts'    => $this->personable->socialAccounts,
                'relationships'     => $this->personable->loadPersonaRelationships(),
                'physicalAttribute' => $this->personable->physicalAttribute,
                'legalDetail'       => $this->personable->legalDetail,
            ];
        }

        $relationships = Relationship::forEntity($this->personable)
            ->with(['personable', 'relatedPersonable'])
            ->get();

        $morphMap = $relationships->pluck('personable_type')
            ->merge($relationships->pluck('related_personable_type'))
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $type) => [$type => ['profile']]);

        $relationships->loadMorph('personable', $morphMap->all());
        $relationships->loadMorph('relatedPersonable', $morphMap->all());

        $profile = Profile::forPersonable($this->personable)->first();

        return [
            'profile'           => $profile?->append('avatar_url'),
            'contacts'          => Contact::forPersonable($this->personable)->get(),
            'addresses'         => Address::forPersonable($this->personable)->get(),
            'documents'         => Document::forPersonable($this->personable)->get(),
            'socialAccounts'    => SocialAccount::forPersonable($this->personable)->get(),
            'relationships'     => $relationships,
            'physicalAttribute' => PhysicalAttribute::forPersonable($this->personable)->first(),
            'legalDetail'       => LegalDetail::forPersonable($this->personable)->first(),
        ];
    }

    // -------------------------------------------------------------------------
    // Contact mutations
    // -------------------------------------------------------------------------

    /**
     * Add a contact value for the scoped model.
     *
     * @param  string  $type        e.g. 'email', 'phone', 'handle'
     * @param  string  $value       Raw value; normalizers run inside ContactManager.
     * @param  bool    $isPrimary   Make this the single primary contact for its type.
     * @param  bool    $isEmergency Mark as an emergency contact.
     */
    public function addContact(
        string $type,
        string $value,
        bool $isPrimary = false,
        bool $isEmergency = false,
    ): Contact {
        return $this->root->contacts()->add(
            $this->personable,
            $type,
            $value,
            $isPrimary,
            $isEmergency,
        );
    }

    /**
     * Promote a contact to primary for its type, enforcing the single-primary invariant.
     *
     * @throws \InvalidArgumentException  If the contact does not belong to this entity.
     */
    public function makeContactPrimary(Contact $contact): void
    {
        $this->root->contacts()->makePrimary($this->personable, $contact);
    }

    /**
     * Delete a contact that belongs to the scoped model.
     *
     * @throws \InvalidArgumentException  If the contact does not belong to this entity.
     */
    public function deleteContact(Contact $contact): bool
    {
        return $this->root->contacts()->delete($this->personable, $contact);
    }

    /**
     * Update the value of a contact that belongs to the scoped model.
     *
     * Keeps the lookup hash in sync and resets the verification status.
     *
     * @param  string  $newValue  Raw value; normalizers run inside ContactManager.
     *
     * @throws \InvalidArgumentException  If the contact does not belong to this entity.
     */
    public function updateContactValue(Contact $contact, string $newValue): Contact
    {
        return $this->root->contacts()->updateValue($this->personable, $contact, $newValue);
    }

    /**
     * Send a verification OTP for the given contact.
     *
     * The OTP is routed strictly to the contact value itself (email or phone),
     * never to the owning profile.
     *
     * @throws \InvalidArgumentException  If the contact does not belong to this entity.
     */
    public function sendContactVerification(Contact $contact): string
    {
        return $this->root->contacts()->sendVerification($this->personable, $contact);
    }

    /**
     * Verify an OTP code for the given contact.
     *
     * Uses a timing-safe comparison and is throttled after five failed attempts.
     *
     * @throws \InvalidArgumentException  If the contact does not belong to this entity.
     */
    public function verifyContact(Contact $contact, string $otp): bool
    {
        return $this->root->contacts()->verify($this->personable, $contact, $otp);
    }

    // -------------------------------------------------------------------------
    // Document mutations
    // -------------------------------------------------------------------------

    /**
     * Add an identity document for the scoped model.
     *
     * @param  string               $type     e.g. 'passport', 'national_id'
     * @param  string               $number   Raw document number; hashed/encrypted by casts.
     * @param  array<string, mixed> $metadata Extra columns, e.g. ['country_code' => 'US'].
     */
    public function addDocument(
        string $type,
        string $number,
        array $metadata = [],
    ): Document {
        return $this->root->documents()->add(
            $this->personable,
            $type,
            $number,
            $metadata,
        );
    }

    /**
     * Delete a document that belongs to the scoped model.
     *
     * Removes its physical files from storage first, then soft-deletes the
     * document record.
     *
     * @throws \InvalidArgumentException  If the document does not belong to this entity.
     */
    public function deleteDocument(Document $document): bool
    {
        return $this->root->documents()->delete($this->personable, $document);
    }

    /**
     * Attach a stored file to a document that belongs to the scoped model.
     *
     * @param  Document    $document  A document record of this entity.
     * @param  string      $filePath  Path of the physical file on the disk.
     * @param  string|null $disk      Storage disk the file lives on (defaults to config).
     * @param  string|null $side      e.g. 'front' / 'back' for identity documents.
     */
    public function attachDocumentFile(
        Document $document,
        string $filePath,
        ?string $disk = null,
        ?string $side = null,
    ): DocumentFile {
        return $this->root->documents()->attachFile($this->personable, $document, $filePath, $disk, $side);
    }

    // -------------------------------------------------------------------------
    // Profile mutations
    // -------------------------------------------------------------------------

    /**
     * Create or update the single Profile record for the scoped model.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(array $attributes): Profile
    {
        return $this->root->profiles()->updateOrCreate($this->personable, $attributes);
    }

    // -------------------------------------------------------------------------
    // Address mutations
    // -------------------------------------------------------------------------

    /**
     * Add an address for the scoped model.
     *
     * @param  string               $type       e.g. 'home', 'work', 'billing'
     * @param  string               $line1      First address line.
     * @param  array<string, mixed> $attributes e.g. ['city' => 'Berlin', 'country_code' => 'DE'].
     * @param  bool                 $isPrimary  Make this the single primary address for its type.
     */
    public function addAddress(
        string $type,
        string $line1,
        array $attributes = [],
        bool $isPrimary = false,
    ): Address {
        return $this->root->addresses()->add(
            $this->personable,
            $type,
            $line1,
            $attributes,
            $isPrimary,
        );
    }

    /**
     * Promote an address to primary for its type.
     *
     * @throws \InvalidArgumentException  If the address does not belong to this entity.
     */
    public function makeAddressPrimary(Address $address): void
    {
        $this->root->addresses()->makePrimary($this->personable, $address);
    }

    /**
     * Delete an address that belongs to the scoped model.
     *
     * @throws \InvalidArgumentException  If the address does not belong to this entity.
     */
    public function deleteAddress(Address $address): bool
    {
        return $this->root->addresses()->delete($this->personable, $address);
    }

    // -------------------------------------------------------------------------
    // Social account mutations
    // -------------------------------------------------------------------------

    /**
     * Attach a social account for the scoped model.
     *
     * @param  string  $platform   Must be listed in config('persona.social_platforms').
     * @param  string  $username   Handle/username on the platform.
     * @param  string|null $url    Optional profile URL.
     * @param  bool    $isPrimary  Make this the single primary account for its platform.
     */
    public function addSocialAccount(
        string $platform,
        string $username,
        ?string $url = null,
        bool $isPrimary = false,
    ): SocialAccount {
        return $this->root->socialAccounts()->add(
            $this->personable,
            $platform,
            $username,
            $url,
            $isPrimary,
        );
    }

    /**
     * Promote a social account to primary for its platform.
     *
     * @throws \InvalidArgumentException  If the account does not belong to this entity.
     */
    public function makeSocialAccountPrimary(SocialAccount $account): void
    {
        $this->root->socialAccounts()->makePrimary($this->personable, $account);
    }

    /**
     * Delete a social account that belongs to the scoped model.
     *
     * @throws \InvalidArgumentException  If the account does not belong to this entity.
     */
    public function deleteSocialAccount(SocialAccount $account): bool
    {
        return $this->root->socialAccounts()->delete($this->personable, $account);
    }

    // -------------------------------------------------------------------------
    // Physical attribute mutations
    // -------------------------------------------------------------------------

    /**
     * Create or update the single PhysicalAttribute record for the scoped model.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updatePhysicalAttributes(array $attributes): PhysicalAttribute
    {
        return $this->root->physicalAttributes()->updateOrCreate($this->personable, $attributes);
    }

    // -------------------------------------------------------------------------
    // Legal detail mutations
    // -------------------------------------------------------------------------

    /**
     * Create or update the single LegalDetail record for the scoped model.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateLegalDetails(array $attributes): LegalDetail
    {
        return $this->root->legalDetails()->updateOrCreate($this->personable, $attributes);
    }

    // -------------------------------------------------------------------------
    // Relationship mutations
    // -------------------------------------------------------------------------

    /**
     * Link the scoped model to a target with the given relationship type.
     *
     * Symmetric types use canonical ordering so A-friend-B and B-friend-A
     * resolve to the same single row. Self-links throw an InvalidArgumentException.
     */
    public function linkTo(Model $target, string $type): Relationship
    {
        return $this->root->relationships()->link(
            $this->personable,
            $target,
            $type,
        );
    }

    /**
     * Remove the relationship of the given type between the scoped model and target.
     *
     * Argument order is irrelevant — canonical ordering is applied internally.
     *
     * @return bool  `true` when a row was found and deleted.
     */
    public function unlinkFrom(Model $target, string $type): bool
    {
        return $this->root->relationships()->unlink(
            $this->personable,
            $target,
            $type,
        );
    }

    // -------------------------------------------------------------------------
    // Nuclear option
    // -------------------------------------------------------------------------

    /**
     * Wipe the entire Persona footprint for the scoped model.
     *
     * Deletes all contacts, documents, addresses, profiles, legal details,
     * physical attributes, social accounts, and relationships (both sides).
     */
    public function forgetAll(): void
    {
        $this->root->forgetAll($this->personable);
    }
}
