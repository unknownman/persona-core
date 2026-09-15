# Laravel Persona: Core

This is the **core data layer** for the [Laravel Persona](https://github.com/unknownman/persona) ecosystem.

**Laravel Persona** is a powerful, headless, and polymorphic data layer for managing person-related data — profiles, contacts, addresses, documents, social accounts, physical attributes, legal details, and relationships — in Laravel applications.

## What is this package?

This package provides everything Persona needs at the data layer: Eloquent models with encrypted/hashed casts, polymorphic morph relations, config-driven managers, verification OTPs, pruneable soft-deletes, and a single fluent entry point (`Persona::for($model)`). It is the foundation that all presentation-layer companions (API, Livewire, Inertia, Blade) build on.

## Installation

```bash
composer require laravel-persona/core
```

Then run the interactive installer:

```bash
php artisan persona:install
```

This will publish the config, migrations, and stubs, and prompt you to set up your `PERSONA_HASH_KEY`.

## Full Documentation

Please refer to the **[Main Repository](https://github.com/unknownman/persona)** for complete installation instructions, API usage, configuration details, and architecture documentation.

---
*Note: This repository is a read-only split of the main monorepo. Please submit all issues and pull requests to the [main repository](https://github.com/unknownman/persona).*
