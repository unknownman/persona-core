<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Persona Hash Key
    |--------------------------------------------------------------------------
    |
    | A unique, application-specific key used to hash and secure sensitive
    | Persona data. Defaults to APP_KEY when PERSONA_HASH_KEY is not set,
    | so the package works out of the box with no extra configuration.
    |
    */

    'hash_key' => env('PERSONA_HASH_KEY', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Encrypt Sensitive Data
    |--------------------------------------------------------------------------
    |
    | Whether sensitive Persona data (contacts values, document numbers,
    | tax identifiers, etc.) should be encrypted at rest using Laravel's
    | Crypt. When disabled, the data layer stores plain text instead.
    |
    */

    'encrypt_sensitive_data' => env('PERSONA_ENCRYPT_SENSITIVE_DATA', true),

    /*
    |--------------------------------------------------------------------------
    | Document Storage
    |--------------------------------------------------------------------------
    |
    | The disk and directory used to store physical document uploads.
    |
    */

    'storage' => [
        'disk' => env('PERSONA_STORAGE_DISK', 'local'),
        'path' => 'persona/documents',
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention (Pruning)
    |--------------------------------------------------------------------------
    |
    | The number of days soft-deleted Contacts and Documents should be retained
    | in the database before being permanently removed by Laravel's prune command.
    |
    */

    'retention' => [
        'soft_deleted_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Orphan Cleanup
    |--------------------------------------------------------------------------
    |
    | The persona:clean-orphans command deletes orphaned Persona rows in
    | batches instead of a single mass query, preventing long-held table locks
    | and memory exhaustion on very large tables. `chunk_size` controls how
    | many orphaned rows are removed per batch.
    |
    */

    'cleanup' => [
        'chunk_size' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | The table names used by the Persona data layer. These are the single
    | source of truth for the package's schema. Override them here if the
    | host application requires different naming conventions.
    |
    */

    'tables' => [
        'profiles' => 'persona_profiles',
        'social_accounts' => 'persona_social_accounts',
        'contacts' => 'persona_contacts',
        'addresses' => 'persona_addresses',
        'documents' => 'persona_documents',
        'document_files' => 'persona_document_files',
        'physical_attributes' => 'persona_physical_attributes',
        'legal_details' => 'persona_legal_details',
        'relationships' => 'persona_relationships',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | The default Eloquent models used by the Persona data layer. The host
    | application may override any of these by binding its own class.
    |
    */

    'models' => [
        'profile' => Persona\Models\Profile::class,
        'social_account' => Persona\Models\SocialAccount::class,
        'contact' => Persona\Models\Contact::class,
        'address' => Persona\Models\Address::class,
        'document' => Persona\Models\Document::class,
        'document_file' => Persona\Models\DocumentFile::class,
        'physical_attribute' => Persona\Models\PhysicalAttribute::class,
        'legal_detail' => Persona\Models\LegalDetail::class,
        'relationship' => Persona\Models\Relationship::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Platforms
    |--------------------------------------------------------------------------
    |
    | The social platforms that are allowed when attaching social accounts
    | to a Persona profile. Extend this list as the application requires.
    |
    */

    'social_platforms' => [
        'twitter',
        'linkedin',
        'github',
        'facebook',
        'instagram',
        'youtube',
        'tiktok',
        'mastodon',
        'discord',
        'threads',
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Types
    |--------------------------------------------------------------------------
    |
    | The allowed document types used to identify a Persona. Each type is a
    | vocabulary term that the Document model and its validators consume.
    |
    */

    'document_types' => [
        'passport',
        'national_id',
        'driving_license',
        'birth_certificate',
        'residence_permit',
        'visa',
        'tax_id',
        'social_security',
    ],

    /*
    |--------------------------------------------------------------------------
    | Address Types
    |--------------------------------------------------------------------------
    |
    | The allowed address types that can be attached to a Persona.
    |
    */

    'address_types' => [
        'home',
        'work',
        'billing',
        'shipping',
        'mailing',
        'temporary',
        'permanent',
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Types
    |--------------------------------------------------------------------------
    |
    | The allowed contact types used when adding contacts to a Persona. The
    | ContactManager validates this list before writing, so invalid types are
    | rejected at the Domain layer. The host may extend this list freely.
    |
    */

    'contact_types' => [
        'email',
        'phone',
        'handle',
        'username',
    ],

    /*
    |--------------------------------------------------------------------------
    | Relationship Types
    |--------------------------------------------------------------------------
    |
    | The vocabulary of relationship types between Personas.
    |
    | "directed" relationships are one-sided referrals (a "parent" refers to
    | a specific person, the inverse being "child"). "symmetric" relationships
    | are mutual by definition (a "spouse" implies the reciprocal is also a
    | "spouse").
    |
    */

    'relationships' => [
        'directed' => [
            'parent',
            'child',
            'guardian',
            'dependent',
            'employer',
            'employee',
        ],
        'symmetric' => [
            'spouse',
            'sibling',
            'friend',
            'partner',
            'relative',
            'colleague',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Relationship Morphs
    |--------------------------------------------------------------------------
    |
    | The morph types that are allowed to be linked as a related personable
    | entity via the API. This prevents morph-type injection vulnerabilities
    | where an attacker could instantiate arbitrary classes.
    |
    */

    'allowed_relationship_morphs' => [],

    /*
    |--------------------------------------------------------------------------
    | OTP Verification
    |--------------------------------------------------------------------------
    |
    | Settings controlling one-time-password verification of contacts. The
    | sms_channel is the notification channel used to deliver OTPs to phone
    | numbers (e.g. 'vonage', 'twilio', 'sms'); no provider is hardcoded here.
    |
    */

    'otp' => [
        'length' => 6,
        'ttl' => 600,
        'max_attempts' => 5,
        'sms_channel' => 'vonage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Statuses
    |--------------------------------------------------------------------------
    |
    | The vocabulary of statuses a Document can transition through. `initial`
    | is the only status the DocumentManager will write on creation; `verified`
    | and `rejected` are written by the verification flow. Host applications
    | may rename these to match their own domain vocabulary.
    |
    */

    'document_statuses' => [
        'initial' => 'pending',
        'verified' => 'verified',
        'rejected' => 'rejected',
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Normalizers
    |--------------------------------------------------------------------------
    |
    | Maps a contact type to the normalizer contract used to canonicalize its
    | value before storage and uniqueness checks. Extend this map to add new
    | contact types or to rewire an existing type to a stronger normalizer.
    |
    */

    'normalizers' => [
        'email' => Persona\Contracts\EmailNormalizerContract::class,
        'phone' => Persona\Contracts\PhoneNormalizerContract::class,
        'handle' => Persona\Contracts\HandleNormalizerContract::class,
        'username' => Persona\Contracts\HandleNormalizerContract::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Manager Fillable Whitelists
    |--------------------------------------------------------------------------
    |
    | The only columns each mutation manager may write through its raw
    | $attributes (or $metadata) array. The morph pair, timestamps, derived
    | *_hash columns and manager-owned invariants (e.g. `status`, `type`,
    | `is_primary`) stay excluded so hosts can never mass-assign them.
    |
    */

    'fillable' => [
        'profile' => [
            'first_name',
            'last_name',
            'middle_name',
            'gender',
            'birth_date',
            'locale',
            'timezone',
        ],
        'social_account' => [
            'url',
        ],
        'address' => [
            'country_code',
            'state',
            'city',
            'zip_code',
            'line_2',
        ],
        'physical_attribute' => [
            'height',
            'weight',
            'eye_color',
            'hair_color',
            'blood_type',
        ],
        'legal_detail' => [
            'nationality',
            'marital_status',
            'tax_id',
        ],
        'document' => [
            'country_code',
            'issued_at',
            'expires_at',
        ],
    ],

];