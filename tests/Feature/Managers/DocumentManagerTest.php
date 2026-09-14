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
}