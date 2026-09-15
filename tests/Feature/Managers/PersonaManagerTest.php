<?php

namespace Persona\Tests\Feature\Managers;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Persona\Events\PersonaDataWiped;
use Persona\Persona;
use Persona\Tests\TestCase;

class PersonaManagerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Populate every Persona table for the given user: all 8 data tables plus a
     * relationship where the user sits on the related (non-personable) side.
     *
     * @return array{user: \Illuminate\Database\Eloquent\Model, document: \Persona\Models\Document}
     */
    private function seedFullFootprint(): array
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        Persona::for($userA)->updateProfile(['first_name' => 'Ali']);
        Persona::for($userA)->addSocialAccount('twitter', 'ali');
        Persona::for($userA)->addContact('email', 'ali@example.com');
        Persona::for($userA)->addAddress('home', '1 Main St');
        $document = Persona::for($userA)->addDocument('passport', 'P123456');
        Persona::for($userA)->updatePhysicalAttributes(['height' => 180]);
        Persona::for($userA)->updateLegalDetails(['nationality' => 'DE']);

        // The wiped user is the related_personable side here.
        Persona::for($userB)->linkTo($userA, 'parent');

        return ['user' => $userA, 'document' => $document];
    }

    private function assertScopedRowCount(int $expected, string $table, string $type, int|string $id): void
    {
        $actual = DB::table($table)
            ->where('personable_type', $type)
            ->where('personable_id', $id)
            ->count();

        $this->assertSame(
            $expected,
            $actual,
            "{$table} scoped to {$type}:{$id} has {$actual} rows, expected {$expected}."
        );
    }

    // -------------------------------------------------------------------------
    // Full wipe
    // -------------------------------------------------------------------------

    public function test_forgetAll_removes_rows_across_all_nine_tables(): void
    {
        DB::statement('PRAGMA foreign_keys = ON;');

        $user = $this->createUser(1);
        $other = $this->createUser(2);

        Persona::for($user)->updateProfile(['first_name' => 'Ali']);
        Persona::for($user)->addSocialAccount('twitter', 'ali');
        Persona::for($user)->addContact('email', 'ali@example.com');
        Persona::for($user)->addAddress('home', '1 Main St');
        $document = Persona::for($user)->addDocument('passport', 'P123456');
        Persona::for($user)->attachDocumentFile($document, 'persona/documents/1/front.jpg');
        Persona::for($user)->updatePhysicalAttributes(['height' => 180]);
        Persona::for($user)->updateLegalDetails(['nationality' => 'DE']);

        // Relationship where $user is the personable side...
        Persona::for($user)->linkTo($other, 'child');
        // ...and one where $user is the related_personable side.
        Persona::for($other)->linkTo($user, 'parent');

        $this->assertSame(1, DB::table(config('persona.tables.profiles'))->count());
        $this->assertSame(1, DB::table(config('persona.tables.social_accounts'))->count());
        $this->assertSame(1, DB::table(config('persona.tables.contacts'))->count());
        $this->assertSame(1, DB::table(config('persona.tables.addresses'))->count());
        $this->assertSame(1, DB::table(config('persona.tables.documents'))->count());
        $this->assertSame(1, DB::table(config('persona.tables.document_files'))->count());
        $this->assertSame(1, DB::table(config('persona.tables.physical_attributes'))->count());
        $this->assertSame(1, DB::table(config('persona.tables.legal_details'))->count());
        $this->assertSame(2, DB::table(config('persona.tables.relationships'))->count());

        Persona::for($user)->forgetAll();

        foreach (config('persona.tables', []) as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} should be empty after forgetAll.");
        }
    }

    // -------------------------------------------------------------------------
    // Scoping (no global TRUNCATE)
    // -------------------------------------------------------------------------

    public function test_forgetAll_does_not_touch_other_entities(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);
        $userC = $this->createUser(3);

        Persona::for($userA)->addContact('email', 'a@example.com');

        Persona::for($userB)->updateProfile(['first_name' => 'Zara']);
        Persona::for($userB)->addSocialAccount('github', 'zara');
        Persona::for($userB)->addContact('email', 'zara@example.com');
        Persona::for($userB)->addAddress('home', '2 Other St');
        $document = Persona::for($userB)->addDocument('national_id', 'N777777');
        Persona::for($userB)->attachDocumentFile($document, 'persona/documents/2/front.jpg');
        Persona::for($userB)->updatePhysicalAttributes(['weight' => 65]);
        Persona::for($userB)->updateLegalDetails(['nationality' => 'FR']);
        Persona::for($userC)->linkTo($userB, 'friend');

        Persona::for($userA)->forgetAll();

        $type = $userB->getMorphClass();

        $this->assertScopedRowCount(1, config('persona.tables.profiles'), $type, $userB->getKey());
        $this->assertScopedRowCount(1, config('persona.tables.social_accounts'), $type, $userB->getKey());
        $this->assertScopedRowCount(1, config('persona.tables.contacts'), $type, $userB->getKey());
        $this->assertScopedRowCount(1, config('persona.tables.addresses'), $type, $userB->getKey());
        $this->assertScopedRowCount(1, config('persona.tables.documents'), $type, $userB->getKey());
        $this->assertSame(1, DB::table(config('persona.tables.document_files'))->where('document_id', $document->getKey())->count());
        $this->assertScopedRowCount(1, config('persona.tables.physical_attributes'), $type, $userB->getKey());
        $this->assertScopedRowCount(1, config('persona.tables.legal_details'), $type, $userB->getKey());
        $this->assertSame(1, DB::table(config('persona.tables.relationships'))->count());
    }

    // -------------------------------------------------------------------------
    // Physical file cleanup
    // -------------------------------------------------------------------------

    public function test_forgetAll_deletes_physical_document_files_from_storage(): void
    {
        Storage::fake('local');

        $user = $this->createUser(1);
        $document = Persona::for($user)->addDocument('passport', 'P123456');

        Storage::disk('local')->put('persona/documents/1/front.jpg', 'binary-contents');

        Persona::for($user)->attachDocumentFile($document, 'persona/documents/1/front.jpg');

        Storage::disk('local')->assertExists('persona/documents/1/front.jpg');

        Persona::for($user)->forgetAll();

        Storage::disk('local')->assertMissing('persona/documents/1/front.jpg');
    }

    // -------------------------------------------------------------------------
    // Related side of relationships
    // -------------------------------------------------------------------------

    public function test_forgetAll_removes_relationships_where_user_is_related_personable(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);
        $userC = $this->createUser(3);

        // userA is the related_personable side.
        Persona::for($userB)->linkTo($userA, 'parent');

        // A relationship that must survive the wipe.
        Persona::for($userB)->linkTo($userC, 'child');

        $relationships = config('persona.tables.relationships');

        $this->assertSame(2, DB::table($relationships)->count());

        Persona::forgetAll($userA);

        // The wiped user's row is gone, the unrelated one survives.
        $this->assertSame(1, DB::table($relationships)->count());
        $this->assertSame(
            1,
            DB::table($relationships)
                ->where('related_personable_type', $userC->getMorphClass())
                ->where('related_personable_id', $userC->getKey())
                ->count()
        );
    }

    // -------------------------------------------------------------------------
    // Wipe event
    // -------------------------------------------------------------------------

    public function test_forgetAll_dispatches_persona_data_wiped_event_exactly_once(): void
    {
        Event::fake();

        $user = $this->createUser(1);
        Persona::for($user)->addContact('email', 'ali@example.com');

        Persona::for($user)->forgetAll();

        Event::assertDispatched(PersonaDataWiped::class, 1);
        Event::assertDispatched(PersonaDataWiped::class, fn (PersonaDataWiped $event) => $event->personable->is($user));
    }

    // -------------------------------------------------------------------------
    // Transactional rollback
    // -------------------------------------------------------------------------

    public function test_forgetAll_rolls_back_every_change_when_a_delete_fails(): void
    {
        DB::statement('PRAGMA foreign_keys = ON;');

        Storage::fake('local');

        ['user' => $user, 'document' => $document] = $this->seedFullFootprint();

        Persona::for($user)->attachDocumentFile($document, 'persona/documents/1/front.jpg');
        Storage::disk('local')->put('persona/documents/1/front.jpg', 'binary-contents');

        // Point a later table (physical_attributes) at a ghost table so the
        // preceding deletes succeed and then the transaction blows up.
        config()->set('persona.tables.physical_attributes', 'nonexistent_persona_table');

        $before = [
            config('persona.tables.profiles') => DB::table(config('persona.tables.profiles'))->count(),
            config('persona.tables.social_accounts') => DB::table(config('persona.tables.social_accounts'))->count(),
            config('persona.tables.contacts') => DB::table(config('persona.tables.contacts'))->count(),
            config('persona.tables.addresses') => DB::table(config('persona.tables.addresses'))->count(),
            config('persona.tables.documents') => DB::table(config('persona.tables.documents'))->count(),
            config('persona.tables.document_files') => DB::table(config('persona.tables.document_files'))->count(),
            config('persona.tables.legal_details') => DB::table(config('persona.tables.legal_details'))->count(),
            config('persona.tables.relationships') => DB::table(config('persona.tables.relationships'))->count(),
        ];

        try {
            Persona::for($user)->forgetAll();

            $this->fail('forgetAll() should have thrown when a delete failed.');
        } catch (QueryException) {
            // Expected: the ghost table does not exist.
        }

        foreach ($before as $table => $count) {
            $this->assertSame(
                $count,
                DB::table($table)->count(),
                "{$table} changed despite the failed transaction."
            );
        }

        Storage::disk('local')->assertExists('persona/documents/1/front.jpg');
    }
}