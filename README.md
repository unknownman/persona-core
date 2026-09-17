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

This will publish the configuration and migration files (plus any detected companion-package assets), prompt you to set your `PERSONA_HASH_KEY` through the built-in key generator, and optionally run the database migrations.

## Full Documentation

Please refer to the **[Main Repository](https://github.com/unknownman/persona)** for complete installation instructions, API usage, configuration details, and architecture documentation.

## Data Cleanup & Orphaned Records

Persona rows point at their owner through a **polymorphic morph pair** (`personable_type` / `personable_id`). Because the target table is unknown at the schema level, **the database cannot enforce a foreign key** — nothing stops a `persona_profiles` row from surviving the User (or Customer, or Employee) it belonged to. Persona mitigates this structural RDBMS limitation at two layers:

### Automated cleanup on Eloquent deletion

The `Persona\Traits\HasPersona` trait registers a **`deleting` hook** on the host model. When an owner is truly removed from the database, its complete Persona footprint (all tables, including both sides of `persona_relationships` and physical document files) is wiped automatically:

```php
$user->delete();       // hard-deletes: wipes the Persona footprint
$user->forceDelete();  // force-deletes: wipes the Persona footprint
```

For models using the `SoftDeletes` trait the two calls behave **differently**, and Persona follows the model's nuance:

| Call | Behavior |
| --- | --- |
| `$user->delete()` | **Soft delete.** The row is flagged with `deleted_at` but still exists, so the Persona data is **preserved** and is restored together with the model. |
| `$user->forceDelete()` | **Hard delete.** The row is removed for real, so the Persona footprint is **wiped automatically**. |

### Sweeping hard orphans with artisan

Eloquent `deleting` events cover graceful deletions, but owners can still vanish through **mass `Model::where(...)->delete()` queries**, DB-level cascades, or raw SQL — none of which fire model events. For those cases, run the built-in orphan sweeper:

```bash
php artisan persona:clean-orphans
```

The command discovers the distinct `personable_type` values stored in every Persona table, resolves each to its backing table (via `Relation::getMorphedModel()` for morph-map aliases), and removes every row whose parent no longer exists using a `WHERE NOT EXISTS` check. It sweeps the `related_personable` side of `persona_relationships` too, and spares rows whose parent is merely soft-deleted.

**Enterprise-ready by design.** Instead of one massive `DELETE`, the sweeper collects orphaned rows as ascending IDs and deletes them in **batches of 1,000** — the `persona.cleanup.chunk_size` config value, or the per-run `--chunk` option. Even with millions of orphans, table locks stay short-lived and memory usage stays flat, because the full orphan set is never loaded into memory at once:

```bash
php artisan persona:clean-orphans              # 1,000 orphans per batch
php artisan persona:clean-orphans --chunk=2500 # tune the batch size
```

Preview before deleting anything:

```bash
php artisan persona:clean-orphans --pretend
```

To run the sweep nightly, schedule it from your `routes/console.php`:

```php
Schedule::command('persona:clean-orphans')->dailyAt('03:00');
```

### Preserving Data on Deletion

Sometimes a deleted entity's Persona data must live on — audit trails, compliance, or reassigning a profile to a future record. Deleting a Customer or Employee is not always a request to forget everything about them.

Opt out of the automatic wipe **per model** with a single expressive property:

```php
use Persona\Traits\HasPersona;

class Customer extends Model
{
    use HasPersona;

    // Retain Persona data even if the Customer record is deleted
    public bool $preservePersonaOnDelete = true;
}
```

With this flag set:

- `$customer->delete()` and `$customer->forceDelete()` leave every profile, contact, address, document, and relationship **intact** in the database. You can later re-attach the data to a fresh record by creating it and assigning the old `personable_id`.
- The `persona:clean-orphans` sweeper **respects the flag too** — it discovers the host model for each distinct `personable_type`, and when preservation is enabled it skips that type entirely:
  ```bash
  php artisan persona:clean-orphans
  # Skipping [Customer] (Preservation enabled)
  ```
- Alternatively, provide a `preservePersonaOnDelete()` method for dynamic, runtime decisions (e.g. retaining data only for legal entities). Preservation is enabled whenever **either** the property or the method returns `true`.

---
*Note: This repository is a read-only split of the main monorepo. Please submit all issues and pull requests to the [main repository](https://github.com/unknownman/persona).*
