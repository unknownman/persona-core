<?php

namespace Persona\Tests\Feature\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Persona\Persona;
use Persona\Tests\TestCase;
use Persona\Traits\HasPersona;

class HasPersonaDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_soft_persona_users', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    // -------------------------------------------------------------------------
    // Scenario 1: Standard delete wipes the footprint.
    // -------------------------------------------------------------------------

    public function test_standard_delete_wipes_persona_data(): void
    {
        $user = PersonaAwareTestUser::create(['id' => 1]);
        $other = $this->createUser(2);

        Persona::for($user)->updateProfile(['first_name' => 'Ali']);
        Persona::for($user)->addContact('email', 'ali@example.com');
        Persona::for($user)->addAddress('home', '1 Main St');
        Persona::for($user)->addSocialAccount('twitter', 'ali');
        Persona::for($other)->linkTo($user, 'parent');

        $user->delete();

        $this->assertSame(0, DB::table(config('persona.tables.contacts'))->count());

        foreach (config('persona.tables', []) as $table) {
            if ($table === config('persona.tables.document_files')) {
                continue;
            }

            $this->assertSame(0, DB::table($table)->count(), "{$table} should be wiped by the deleting hook.");
        }

        $this->assertSame(0, DB::table(config('persona.tables.document_files'))->count());
    }

    // -------------------------------------------------------------------------
    // Scenario 2: Soft delete preserves the persona footprint.
    // -------------------------------------------------------------------------

    public function test_soft_delete_preserves_persona_data(): void
    {
        $user = SoftPersonaUser::create(['id' => 1]);

        Persona::for($user)->addContact('email', 'ali@example.com');
        Persona::for($user)->updateProfile(['first_name' => 'Ali']);

        $user->delete();

        $this->assertTrue($user->trashed());

        $this->assertSame(1, DB::table(config('persona.tables.contacts'))
            ->where('personable_id', 1)
            ->where('personable_type', $user->getMorphClass())
            ->count());

        $this->assertSame(1, DB::table(config('persona.tables.profiles'))
            ->where('personable_id', 1)
            ->where('personable_type', $user->getMorphClass())
            ->count());
    }

    // -------------------------------------------------------------------------
    // Scenario 3: Force delete on a soft-deleting model wipes the footprint.
    // -------------------------------------------------------------------------

    public function test_force_delete_wipes_persona_data(): void
    {
        $user = SoftPersonaUser::create(['id' => 1]);

        Persona::for($user)->addContact('email', 'ali@example.com');
        Persona::for($user)->updateProfile(['first_name' => 'Ali']);

        $user->forceDelete();

        $this->assertSame(0, DB::table(config('persona.tables.contacts'))
            ->where('personable_id', 1)
            ->where('personable_type', $user->getMorphClass())
            ->count());

        $this->assertSame(0, DB::table(config('persona.tables.profiles'))
            ->where('personable_id', 1)
            ->where('personable_type', $user->getMorphClass())
            ->count());
    }

    // -------------------------------------------------------------------------
    // Scenario 4: $preservePersonaOnDelete keeps the footprint on delete.
    // -------------------------------------------------------------------------

    public function test_preserve_persona_on_delete_flag_keeps_data(): void
    {
        $user = PreservedPersonaTestUser::create(['id' => 1]);

        Persona::for($user)->addContact('email', 'ali@example.com');
        Persona::for($user)->updateProfile(['first_name' => 'Ali']);

        $user->delete();

        $this->assertSame(1, DB::table(config('persona.tables.contacts'))
            ->where('personable_id', 1)
            ->where('personable_type', $user->getMorphClass())
            ->count());

        $this->assertSame(1, DB::table(config('persona.tables.profiles'))
            ->where('personable_id', 1)
            ->where('personable_type', $user->getMorphClass())
            ->count());
    }

    // -------------------------------------------------------------------------
    // Supplementary: a wipe never bleeds into other entities.
    // -------------------------------------------------------------------------

    public function test_deleting_model_leaves_other_entities_untouched(): void
    {
        $user = PersonaAwareTestUser::create(['id' => 1]);
        $other = $this->createUser(2);

        Persona::for($user)->addContact('email', 'ali@example.com');
        Persona::for($other)->updateProfile(['first_name' => 'Zara']);
        Persona::for($other)->addContact('email', 'zara@example.com');

        $user->delete();

        $contacts = config('persona.tables.contacts');

        $this->assertSame(0, DB::table($contacts)
            ->where('personable_id', $user->getKey())
            ->where('personable_type', $user->getMorphClass())
            ->count());

        $this->assertSame(1, DB::table($contacts)
            ->where('personable_id', $other->getKey())
            ->where('personable_type', $other->getMorphClass())
            ->count());

        $this->assertSame(1, DB::table(config('persona.tables.profiles'))
            ->where('personable_id', $other->getKey())
            ->where('personable_type', $other->getMorphClass())
            ->count());
    }
}

/**
 * A TestUser variant that carries the Persona trait so we can exercise
 * the automatic `deleting` hook without touching the shared TestUser.
 */
class PersonaAwareTestUser extends Model
{
    use HasPersona;

    protected $guarded = [];

    protected $table = 'test_users';
}

/**
 * A soft-deleting TestUser variant so we can prove that soft deletes
 * preserve Persona data and only force deletes wipe it.
 */
class SoftPersonaUser extends Model
{
    use HasPersona;
    use SoftDeletes;

    protected $guarded = [];

    protected $table = 'test_soft_persona_users';
}

/**
 * A TestUser variant that retains its Persona data after deletion.
 */
class PreservedPersonaTestUser extends Model
{
    use HasPersona;

    protected $guarded = [];

    protected $table = 'test_users';

    public bool $preservePersonaOnDelete = true;
}