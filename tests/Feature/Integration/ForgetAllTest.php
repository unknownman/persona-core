<?php

namespace Persona\Tests\Feature\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Persona\Models\Contact;
use Persona\Persona;
use Persona\Tests\TestCase;
use Persona\Tests\TestUser;

class ForgetAllTest extends TestCase
{
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser();
    }

    private function rowCount(string $tableKey): int
    {
        return DB::table(config('persona.tables.' . $tableKey))
            ->where('personable_type', $this->user->getMorphClass())
            ->where('personable_id', $this->user->getKey())
            ->count();
    }

    public function test_forgetAll_wipes_every_persona_table_for_the_entity(): void
    {
        Persona::for($this->user)->updateProfile(['first_name' => 'Alice']);
        Persona::for($this->user)->addContact('email', 'alice@example.com');
        Persona::for($this->user)->addAddress('home', '123 Main St');
        Persona::for($this->user)->addDocument('passport', 'AA123456');
        Persona::for($this->user)->addSocialAccount('github', 'alice-gh');
        Persona::for($this->user)->updatePhysicalAttributes(['height' => 170]);
        Persona::for($this->user)->updateLegalDetails(['nationality' => 'US']);

        Persona::for($this->user)->forgetAll();

        foreach ([
            'profiles',
            'contacts',
            'addresses',
            'documents',
            'social_accounts',
            'physical_attributes',
            'legal_details',
        ] as $table) {
            $this->assertSame(0, $this->rowCount($table), "Table [{$table}] was not wiped.");
        }
    }

    public function test_forgetAll_via_static_entry_point_behaves_identically(): void
    {
        Persona::for($this->user)->addContact('email', 'alice@example.com');

        Persona::forgetAll($this->user);

        $this->assertSame(0, $this->rowCount('contacts'));
    }

    public function test_forgetAll_removes_relationships_from_both_sides(): void
    {
        $target = $this->createUser(id: 2);

        Persona::for($this->user)->linkTo($target, 'friend');

        $this->assertSame(1, $this->relationshipCountFor($this->user));
        $this->assertSame(1, $this->relationshipCountFor($target));

        Persona::for($this->user)->forgetAll();

        $this->assertSame(0, $this->relationshipCountFor($this->user));
        $this->assertSame(0, $this->relationshipCountFor($target));
    }

    public function test_forgetAll_does_not_touch_other_entities(): void
    {
        $other = $this->createUser(id: 2);

        Persona::for($this->user)->addContact('email', 'alice@example.com');
        Persona::for($other)->addContact('email', 'bob@example.com');

        Persona::for($this->user)->forgetAll();

        $this->assertSame(0, $this->rowCount('contacts'));

        $survivor = Contact::where('personable_type', $other->getMorphClass())
            ->where('personable_id', $other->getKey())
            ->first();

        $this->assertEquals('bob@example.com', $survivor->value);
    }

    public function test_forgetAll_removes_soft_deleted_documents_too(): void
    {
        $document = Persona::for($this->user)->addDocument('passport', 'AA123456');
        $document->delete();

        $this->assertSame(1, DB::table(config('persona.tables.documents'))
            ->where('personable_type', $this->user->getMorphClass())
            ->where('personable_id', $this->user->getKey())
            ->count());

        Persona::for($this->user)->forgetAll();

        $this->assertSame(0, DB::table(config('persona.tables.documents'))
            ->where('personable_type', $this->user->getMorphClass())
            ->where('personable_id', $this->user->getKey())
            ->count());
    }

    public function test_forgetAll_removes_physical_document_files_after_db_transaction(): void
    {
        Storage::fake('local');

        $document = Persona::for($this->user)->addDocument('passport', 'AA123456');
        Persona::for($this->user)->attachDocumentFile($document, 'documents/aa123456/front.png');

        Storage::disk('local')->put('documents/aa123456/front.png', 'binary-image-data');
        $this->assertTrue(Storage::disk('local')->exists('documents/aa123456/front.png'));

        Persona::for($this->user)->forgetAll();

        $this->assertFalse(Storage::disk('local')->exists('documents/aa123456/front.png'));
        $this->assertSame(0, DB::table(config('persona.tables.documents'))->where('personable_type', $this->user->getMorphClass())->count());
    }

    public function test_forgetAll_is_idempotent(): void
    {
        Persona::for($this->user)->addContact('email', 'alice@example.com');

        Persona::for($this->user)->forgetAll();
        Persona::for($this->user)->forgetAll();

        $this->assertSame(0, $this->rowCount('contacts'));
    }

    private function relationshipCountFor(TestUser $user): int
    {
        return DB::table(config('persona.tables.relationships'))
            ->where(fn ($query) => $query
                ->where('personable_type', $user->getMorphClass())
                ->where('personable_id', $user->getKey()))
            ->orWhere(fn ($query) => $query
                ->where('related_personable_type', $user->getMorphClass())
                ->where('related_personable_id', $user->getKey()))
            ->count();
    }
}