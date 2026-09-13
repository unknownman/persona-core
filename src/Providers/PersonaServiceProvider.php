<?php

namespace Persona\Providers;

use Illuminate\Support\ServiceProvider;
use Persona\Contracts\CountryNormalizerContract;
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
use Persona\Normalizers\DefaultCountryNormalizer;
use Persona\Normalizers\DefaultEmailNormalizer;
use Persona\Normalizers\DefaultHandleNormalizer;
use Persona\Normalizers\DefaultPhoneNormalizer;
use Persona\Services\NullDocumentVerificationProvider;
use Persona\Services\NullSocialActivityResolver;
use RuntimeException;

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

        $this->app->singleton(ContactManager::class, function ($app) {
            return new ContactManager($app);
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

    /**
     * Boot the package services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'persona-migrations');

        $this->publishes([
            __DIR__ . '/../../config/persona.php' => config_path('persona.php'),
        ], 'persona-config');

        $this->assertHashKeyIsConfigured();
    }

    /**
     * Ensure that the PERSONA_HASH_KEY environment value is present.
     *
     * If the configured hash key is null or empty, booting will fail to
     * signal that sensitive Persona hashes can not be secured.
     *
     * @throws \RuntimeException
     */
    protected function assertHashKeyIsConfigured(): void
    {
        $hashKey = config('persona.hash_key');

        if (is_null($hashKey) || $hashKey === '') {
            throw new RuntimeException(
                'PERSONA_HASH_KEY is missing or empty. Please generate a unique key and set it in your .env file to secure sensitive Persona hashes.'
            );
        }
    }
}