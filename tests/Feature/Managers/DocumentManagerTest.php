<?php

namespace Persona\Tests\Feature\Managers;

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
}