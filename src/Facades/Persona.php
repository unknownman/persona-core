<?php

namespace Persona\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
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
 * The official Laravel Facade for the Persona package, bound to the root
 * PersonaManager.
 *
 * Static calls forward to the container-resolved PersonaManager and behave
 * identically to {@see \Persona\Persona}. `Persona::for()` is forwarded to
 * the canonical fluent entry point so scoped usage is unambiguous:
 *
 *   Persona::for($user)->updateProfile(['first_name' => 'Ali']);
 *   Persona::contacts()->add($user, 'email', 'ali@example.com');
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
 * @method static void forgetAll(Model $personable)
 *
 * @see \Persona\Persona
 * @see \Persona\Managers\PersonaManager
 * @see \Persona\Managers\ScopedPersonaManager
 */
class Persona extends Facade
{
    /**
     * Create a ScopedPersonaManager bound to the given model.
     */
    public static function for(Model $model): ScopedPersonaManager
    {
        return \Persona\Persona::for($model);
    }

    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return PersonaManager::class;
    }
}