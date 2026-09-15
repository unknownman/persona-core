<?php

namespace Persona\Tests\Feature\Managers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Persona\Contracts\DocumentVerificationProvider;
use Persona\Models\Document;
use Persona\Persona;
use Persona\Tests\TestCase;

class DocumentManagerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // IDOR protection
    // -------------------------------------------------------------------------

    public function test_attach_file_to_document_not_owned_throws(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        $document = Persona::for($userA)->addDocument('passport', 'A1234567', ['country_code' => 'US']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong');

        Persona::for($userB)->attachDocumentFile($document, 'persona/documents/1/front.jpg');
    }

    public function test_attach_file_to_owned_document_succeeds(): void
    {
        $user = $this->createUser();

        $document = Persona::for($user)->addDocument('passport', 'A1234567', ['country_code' => 'US']);

        $file = Persona::for($user)->attachDocumentFile(
            $document,
            'persona/documents/1/front.jpg',
            'local',
            'front',
        );

        $this->assertEquals($document->getKey(), $file->document_id);
        $this->assertSame('persona/documents/1/front.jpg', $file->file_path);
        $this->assertSame('local', $file->disk);
        $this->assertSame('front', $file->side);
    }

    // -------------------------------------------------------------------------
    // Metadata whitelist
    // -------------------------------------------------------------------------

    public function test_unknown_metadata_key_is_not_mass_assigned(): void
    {
        $user = $this->createUser();

        $document = Persona::for($user)->addDocument(
            'passport',
            'X9999999',
            ['country_code' => 'US', 'is_flagged' => true],
        );

        $this->assertSame('US', $document->fresh()->country_code);
        $this->assertSame('pending', $document->fresh()->status);
        $this->assertArrayNotHasKey('is_flagged', $document->fresh()->getAttributes());
    }

    public function test_country_code_is_set_through_metadata(): void
    {
        $user = $this->createUser();

        $document = Persona::for($user)->addDocument(
            'passport',
            'Z8888888',
            ['country_code' => 'DE', 'issued_at' => '2024-01-15', 'expires_at' => '2034-01-15'],
        );

        $fresh = $document->fresh();

        $this->assertSame('DE', $fresh->country_code);
        $this->assertEquals('2024-01-15', $fresh->issued_at->format('Y-m-d'));
        $this->assertEquals('2034-01-15', $fresh->expires_at->format('Y-m-d'));
    }

    // -------------------------------------------------------------------------
    // Config-driven status vocabulary
    // -------------------------------------------------------------------------

    public function test_status_helpers_and_scopes_read_from_document_statuses_config(): void
    {
        $this->app['config']->set([
            'persona.document_statuses.initial' => 'draft',
            'persona.document_statuses.verified' => 'approved',
            'persona.document_statuses.rejected' => 'denied',
        ]);

        Notification::fake();

        $user = $this->createUser();

        $pending = Persona::for($user)->addDocument('passport', 'A1234567');
        $verified = Persona::for($user)->addDocument('national_id', 'B1234567');

        $this->assertTrue(Document::pending()->whereKey($pending->getKey())->exists());

        $this->app->instance(DocumentVerificationProvider::class, new class implements DocumentVerificationProvider {
            public function verify(Model $document): bool
            {
                return true;
            }
        });

        Persona::for($user)->root()->documents()->verify($verified);

        $this->assertSame('approved', $verified->fresh()->status);
        $this->assertTrue($verified->isVerified());
        $this->assertSame(1, Document::currentlyValid()->count());

        $rejected = Persona::for($user)->addDocument('driving_license', 'C1234567');
        $this->app->instance(DocumentVerificationProvider::class, new class implements DocumentVerificationProvider {
            public function verify(Model $document): bool
            {
                return false;
            }
        });

        Persona::for($user)->root()->documents()->verify($rejected);

        $this->assertSame('denied', $rejected->fresh()->status);
        $this->assertTrue(Document::rejected()->whereKey($rejected->getKey())->exists());
        $this->assertFalse($rejected->isVerified());
    }

    // -------------------------------------------------------------------------
    // Config-driven storage disk
    // -------------------------------------------------------------------------

    public function test_attach_file_defaults_disk_from_storage_config(): void
    {
        $this->app['config']->set('persona.storage.disk', 's3');

        $user = $this->createUser();
        $document = Persona::for($user)->addDocument('passport', 'A1234567');

        $file = Persona::for($user)->attachDocumentFile(
            $document,
            'persona/documents/1/front.jpg',
        );

        $this->assertSame('s3', $file->fresh()->disk);
    }

    public function test_attach_file_explicit_disk_overrides_config(): void
    {
        $this->app['config']->set('persona.storage.disk', 's3');

        $user = $this->createUser();
        $document = Persona::for($user)->addDocument('passport', 'B9999999');

        $file = Persona::for($user)->attachDocumentFile(
            $document,
            'persona/documents/2/front.jpg',
            disk: 'r2',
        );

        $this->assertSame('r2', $file->fresh()->disk);
    }
}