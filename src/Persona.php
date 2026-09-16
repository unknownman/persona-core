<?php

namespace Persona;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Persona\Managers\AddressManager;
use Persona\Managers\ContactManager;
use Persona\Managers\DocumentManager;
use Persona\Managers\LegalDetailManager;
use Persona\Managers\PersonaManager;
use Persona\Managers\PhysicalAttributeManager;
use Persona\Managers\ProfileManager;
use Persona\Managers\RelationshipManager;
use Persona\Managers\ScopedPersonaManager;
use Persona\Managers\SocialAccountManager;

/**
 * The canonical fluent entry point for the Persona package.
 *
 * Use {@see \Persona\Facades\Persona} when you want a Laravel Facade bound to
 * the container; use this class when you want the plain, dependency-free
 * entry point. Both accept identical static calls.
 *
 *   $user->persona()->addContact('email', 'ali@example.com'); // HasPersona trait
 *   Persona::for($user)->linkTo($company, 'employer');        // scoped
 *   Persona::contacts()->add(...);                            // root manager
 *
 * @method static ScopedPersonaManager for(Model $model)
 * @method static ContactManager contacts()
 * @method static DocumentManager documents()
 * @method static RelationshipManager relationships()
 * @method static ProfileManager profiles()
 * @method static AddressManager addresses()
 * @method static SocialAccountManager socialAccounts()
 * @method static PhysicalAttributeManager physicalAttributes()
 * @method static LegalDetailManager legalDetails()
 * @method static void forgetAll(Model $model)
 * @method static PersonaManager manager()
 *
 * @see \Persona\Facades\Persona
 * @see \Persona\Managers\PersonaManager
 * @see \Persona\Managers\ScopedPersonaManager
 */
class Persona
{
    /**
     * The custom callback used to hash persona lookup columns.
     *
     * @var callable|null
     */
    public static $hashCallback;

    /**
     * Set a custom callback to be used for hashing persona lookup columns.
     */
    public static function hashUsing(callable $callback): void
    {
        static::$hashCallback = $callback;
    }

    /**
     * Create a ScopedPersonaManager bound to the given model.
     *
     * This is the primary entry point for all scoped mutations:
     *
     *   $user->persona()->addContact('email', 'ali@example.com');
     *   Persona::for($user)->linkTo($company, 'employer');
     */
    public static function for(Model $model): ScopedPersonaManager
    {
        return new ScopedPersonaManager($model, static::manager());
    }

    /**
     * Wipe the complete Persona footprint for a personable model.
     */
    public static function forgetAll(Model $model): void
    {
        static::manager()->forgetAll($model);
    }

    /**
     * Resolve the root PersonaManager from the container.
     */
    public static function manager(): PersonaManager
    {
        return Container::getInstance()->make(PersonaManager::class);
    }

    /**
     * Dynamically forward static calls to the root PersonaManager.
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::manager()->{$method}(...$arguments);
    }
}