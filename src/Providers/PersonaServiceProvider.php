<?php

namespace Persona\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Persona\Commands\CleanOrphansCommand;
use Persona\Commands\InstallCommand;
use Persona\Commands\KeyGenerateCommand;
use Persona\Contracts\AvatarResolverContract;
use Persona\Contracts\CountryNormalizerContract;
use Persona\Contracts\DocumentPathGeneratorContract;
use Persona\Contracts\DocumentVerificationProvider;
use Persona\Contracts\EmailNormalizerContract;
use Persona\Contracts\HandleNormalizerContract;
use Persona\Contracts\PhoneNormalizerContract;
use Persona\Contracts\SocialActivityResolverContract;
use Persona\Managers\AddressManager;
use Persona\Managers\ContactManager;
use Persona\Managers\DocumentManager;
use Persona\Managers\LegalDetailManager;
use Persona\Managers\PersonaManager;
use Persona\Managers\PhysicalAttributeManager;
use Persona\Managers\ProfileManager;
use Persona\Managers\RelationshipManager;
use Persona\Managers\SocialAccountManager;
use Persona\Models\Address;
use Persona\Models\Contact;
use Persona\Models\Document;
use Persona\Models\LegalDetail;
use Persona\Models\PhysicalAttribute;
use Persona\Models\Profile;
use Persona\Models\SocialAccount;
use Persona\Normalizers\DefaultCountryNormalizer;
use Persona\Normalizers\DefaultEmailNormalizer;
use Persona\Normalizers\DefaultHandleNormalizer;
use Persona\Normalizers\DefaultPhoneNormalizer;
use Persona\Policies\PersonaPolicy;
use Persona\Services\DefaultDocumentPathGenerator;
use Persona\Services\NullAvatarResolver;
use Persona\Services\NullDocumentVerificationProvider;
use Persona\Services\NullSocialActivityResolver;

class PersonaServiceProvider extends ServiceProvider
{
    /**
     * Register the services and contract bindings for the package.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/persona.php', 'persona');

        $this->app->singleton(EmailNormalizerContract::class, DefaultEmailNormalizer::class);
        $this->app->singleton(PhoneNormalizerContract::class, DefaultPhoneNormalizer::class);
        $this->app->singleton(HandleNormalizerContract::class, DefaultHandleNormalizer::class);
        $this->app->singleton(CountryNormalizerContract::class, DefaultCountryNormalizer::class);
        $this->app->singleton(DocumentVerificationProvider::class, NullDocumentVerificationProvider::class);
        $this->app->singleton(SocialActivityResolverContract::class, NullSocialActivityResolver::class);
        $this->app->singleton(DocumentPathGeneratorContract::class, DefaultDocumentPathGenerator::class);
        $this->app->singleton(AvatarResolverContract::class, NullAvatarResolver::class);

        $this->app->singleton(ContactManager::class, function () {
            return new ContactManager();
        });

        $this->app->singleton(DocumentManager::class, function () {
            return new DocumentManager();
        });

        $this->app->singleton(RelationshipManager::class, function () {
            return new RelationshipManager();
        });

        $this->app->singleton(ProfileManager::class, function () {
            return new ProfileManager();
        });

        $this->app->singleton(AddressManager::class, function () {
            return new AddressManager();
        });

        $this->app->singleton(SocialAccountManager::class, function () {
            return new SocialAccountManager();
        });

        $this->app->singleton(PhysicalAttributeManager::class, function () {
            return new PhysicalAttributeManager();
        });

        $this->app->singleton(LegalDetailManager::class, function () {
            return new LegalDetailManager();
        });

        $this->app->singleton(PersonaManager::class, function ($app) {
            return new PersonaManager(
                $app->make(ContactManager::class),
                $app->make(DocumentManager::class),
                $app->make(RelationshipManager::class),
                $app->make(ProfileManager::class),
                $app->make(AddressManager::class),
                $app->make(SocialAccountManager::class),
                $app->make(PhysicalAttributeManager::class),
                $app->make(LegalDetailManager::class),
            );
        });

        $this->app->alias(PersonaManager::class, 'persona');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'persona-migrations');

        $this->publishes([
            __DIR__ . '/../../config/persona.php' => config_path('persona.php'),
        ], 'persona-config');

        // One shared policy gates raw, unmasked PII across every Persona row.
        // The policy resolves the polymorphic owner itself, so hosts can call
        // $user->can('viewSensitive', $contact) straight from any serialization
        // layer (JsonResources, controllers, Livewire components, ...).
        foreach ($this->maskedModels() as $model) {
            Gate::policy($model, PersonaPolicy::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanOrphansCommand::class,
                InstallCommand::class,
                KeyGenerateCommand::class,
            ]);
        }
    }

    /**
     * Persona models whose serialized resources expose sensitive fields that
     * must be masked until `viewSensitive` is authorized.
     *
     * @return array<class-string<\Illuminate\Database\Eloquent\Model>>
     */
    private function maskedModels(): array
    {
        return [
            Profile::class,
            Contact::class,
            Address::class,
            Document::class,
            SocialAccount::class,
            PhysicalAttribute::class,
            LegalDetail::class,
        ];
    }
}