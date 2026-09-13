<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Persona Hash Key
    |--------------------------------------------------------------------------
    |
    | A unique, application-specific key used to hash and secure sensitive
    | Persona data. This MUST be set in your .env file as PERSONA_HASH_KEY.
    | The service provider refuses to boot when this value is missing.
    |
    */

    'hash_key' => env('PERSONA_HASH_KEY'),

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

];