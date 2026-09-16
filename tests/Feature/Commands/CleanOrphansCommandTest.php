<?php

namespace Persona\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Persona\Persona;
use Persona\Tests\TestCase;
use Persona\Traits\HasPersona;

class CleanOrphansCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_soft_users', function ($table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    // -------------------------------------------------------------------------
    // Scenario 5: the sweeper removes orphans left by a plain-query delete.
    // -------------------------------------------------------------------------

    public function test_sweeper_removes_orphans_after_plain_query_delete(): void
    {
        $user = $this->createUser(1);

        Persona::for($user)->updateProfile(['first_name' => 'Ali']);
        Persona::for($user)->addSocialAccount('github', 'zara');
        Persona::for($user)->addContact('email', 'ali@example.com');
        Persona::for($user)->addAddress('home', '1 Main St');
        Persona::for($user)->addDocument('passport', 'P123456');
        Persona::for($user)->updatePhysicalAttributes(['height' => 180]);
        Persona::for($user)->updateLegalDetails(['nationality' => 'DE']);

        // A plain query bypassing Eloquent events — everything orphans now.
        DB::table('test_users')->where('id', $user->getKey())->delete();

        $this->artisan('persona:clean-orphans')->assertSuccessful();

        foreach (['profiles', 'social_accounts', 'contacts', 'addresses', 'documents', 'physical_attributes', 'legal_details'] as $table) {
            $this->assertSame(
                0,
                DB::table(config("persona.tables.{$table}"))->count(),
                "{$table} should be swept after its parent was hard-deleted."
            );
        }
    }

    // -------------------------------------------------------------------------
    // Scenario 6: the sweeper skips morph types with preservation enabled.
    // -------------------------------------------------------------------------

    public function test_sweeper_skips_preserved_types(): void
    {
        $normal = $this->createUser(1);
        $preserved = PreservedTestUser::create(['id' => 2]);

        Persona::for($normal)->addContact('email', 'normal@example.com');
        Persona::for($preserved)->addContact('email', 'kept@example.com');

        // Both parents vanish via a plain query...
        DB::table('test_users')->whereIn('id', [1, 2])->delete();

        $this->artisan('persona:clean-orphans')
            ->expectsOutputToContain('Skipping [PreservedTestUser] (Preservation enabled)')
            ->assertSuccessful();

        $contacts = config('persona.tables.contacts');

        // ...the preserved type's orphaned rows survive the sweep.
        $this->assertSame(0, DB::table($contacts)->where('personable_id', 1)->count());
        $this->assertSame(1, DB::table($contacts)->where('personable_id', 2)->count());
    }

    // -------------------------------------------------------------------------
    // Enterprise safety: 10,000 orphans are swept in batches of 1,000.
    // -------------------------------------------------------------------------

    public function test_sweeper_chunks_ten_thousand_orphans_in_batches(): void
    {
        $user = $this->createUser(1);

        $orphanRows = [];

        for ($i = 0; $i < 10000; $i++) {
            $orphanRows[] = [
                'personable_type' => $user->getMorphClass(),
                'personable_id' => 999,
                'type' => 'email',
                'value' => "orphan{$i}@example.com",
                'value_hash' => "orphan-hash-{$i}",
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($orphanRows) === 1000) {
                DB::table(config('persona.tables.contacts'))->insert($orphanRows);
                $orphanRows = [];
            }
        }

        $this->assertSame(10000, DB::table(config('persona.tables.contacts'))->count());

        $deleteCount = 0;

        DB::listen(function ($query) use (&$deleteCount) {
            if (str_starts_with($query->sql, 'delete')) {
                $deleteCount++;
            }
        });

        $this->artisan('persona:clean-orphans')->assertSuccessful();

        // 10,000 orphans / 1,000 per batch = exactly 10 batched deletions.
        $this->assertSame(10, $deleteCount, 'The sweeper should delete in batches of 1,000.');
        $this->assertSame(0, DB::table(config('persona.tables.contacts'))->count());
    }

    // -------------------------------------------------------------------------
    // Supplementary sweeper behavior.
    // -------------------------------------------------------------------------

    public function test_sweeper_removes_relationships_on_either_orphaned_side(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);
        $userC = $this->createUser(3);

        Persona::for($userA)->linkTo($userB, 'parent'); // A owns the row.
        Persona::for($userB)->linkTo($userC, 'friend'); // B owns the row.

        // Hard-delete B: it is the related side of the first row AND the
        // personable side of the second.
        DB::table('test_users')->where('id', $userB->getKey())->delete();

        $this->artisan('persona:clean-orphans')->assertSuccessful();

        $this->assertSame(0, DB::table(config('persona.tables.relationships'))->count());
    }

    public function test_sweeper_spares_rows_backed_by_soft_deleted_parents(): void
    {
        $user = $this->createUser(1);
        SoftTestUser::create(['id' => 2]);

        Persona::for($user)->addContact('email', 'ali@example.com');
        Persona::for(SoftTestUser::findOrFail(2))->addContact('email', 'zombie@example.com');
        Persona::for(SoftTestUser::findOrFail(2))->updateProfile(['first_name' => 'Zombie']);

        // Soft-deleting does NOT orphan: the parent row still exists.
        SoftTestUser::findOrFail(2)->delete();

        // Hard-delete the first parent via a plain query.
        DB::table('test_users')->where('id', $user->getKey())->delete();

        $this->artisan('persona:clean-orphans')->assertSuccessful();

        $contacts = config('persona.tables.contacts');

        // Rows for the hard-missing user are swept...
        $this->assertSame(0, DB::table($contacts)->where('personable_id', $user->getKey())->count());

        // ...while rows for the soft-deleted parent survive.
        $this->assertSame(1, DB::table($contacts)->where('personable_id', 2)->count());
        $this->assertSame(1, DB::table(config('persona.tables.profiles'))->where('personable_id', 2)->count());
    }

    public function test_sweeper_pretend_reports_orphans_without_deleting(): void
    {
        $user = $this->createUser(1);

        Persona::for($user)->addContact('email', 'ali@example.com');

        DB::table('test_users')->where('id', $user->getKey())->delete();

        $this->artisan('persona:clean-orphans', ['--pretend' => true])
            ->expectsOutputToContain('orphaned')
            ->assertSuccessful();

        $this->assertSame(1, DB::table(config('persona.tables.contacts'))->count());
    }
}

/**
 * A parent model that soft-deletes so we can assert the command spares
 * rows pointing at soft-deleted (still present) parents.
 */
class SoftTestUser extends Model
{
    use HasPersona;
    use SoftDeletes;

    protected $guarded = [];

    protected $table = 'test_soft_users';
}

/**
 * A parent model that opts out of automatic Persona deletion.
 */
class PreservedTestUser extends Model
{
    use HasPersona;

    protected $guarded = [];

    protected $table = 'test_users';

    public bool $preservePersonaOnDelete = true;
}