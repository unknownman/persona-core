<?php

namespace Persona\Tests\Feature\Managers;

use Persona\Models\Relationship;
use Persona\Persona;
use Persona\Tests\TestCase;

class RelationshipManagerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Symmetric canonical ordering
    // -------------------------------------------------------------------------

    public function test_symmetric_linking_in_both_directions_produces_single_row(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        Persona::for($userA)->linkTo($userB, 'friend');
        Persona::for($userB)->linkTo($userA, 'friend');

        $this->assertEquals(1, Relationship::count(), 'A→B and B→A should collapse to one row');
    }

    public function test_symmetric_link_returns_existing_model_on_second_call(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        $first = Persona::for($userA)->linkTo($userB, 'friend');
        $second = Persona::for($userB)->linkTo($userA, 'friend');

        $this->assertEquals($first->getKey(), $second->getKey());
    }

    public function test_canonical_ordering_smaller_morph_first(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(100);

        // userA (testuser:1) should be the personable side (lexicographically smaller).
        $rel = Persona::for($userB)->linkTo($userA, 'friend');

        $this->assertEquals($userA->getMorphClass(), $rel->personable_type);
        $this->assertEquals($userA->getKey(), (int) $rel->personable_id);
        $this->assertEquals($userB->getMorphClass(), $rel->related_personable_type);
        $this->assertEquals($userB->getKey(), (int) $rel->related_personable_id);
    }

    // -------------------------------------------------------------------------
    // Directed relationships keep source order
    // -------------------------------------------------------------------------

    public function test_directed_link_preserves_source_target_order(): void
    {
        $parent = $this->createUser(1);
        $child = $this->createUser(2);

        $rel = Persona::for($parent)->linkTo($child, 'parent');

        $this->assertEquals($parent->getMorphClass(), $rel->personable_type);
        $this->assertEquals($child->getMorphClass(), $rel->related_personable_type);
        $this->assertEquals(1, Relationship::count());
    }

    public function test_directed_link_reverse_order_creates_second_row(): void
    {
        $parent = $this->createUser(1);
        $child = $this->createUser(2);

        Persona::for($parent)->linkTo($child, 'parent');
        Persona::for($child)->linkTo($parent, 'child');

        $this->assertEquals(2, Relationship::count());
    }

    // -------------------------------------------------------------------------
    // Self-linking guard
    // -------------------------------------------------------------------------

    public function test_self_linking_throws(): void
    {
        $user = $this->createUser(1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be linked to itself');

        Persona::for($user)->linkTo($user, 'friend');
    }

    // -------------------------------------------------------------------------
    // Unlink
    // -------------------------------------------------------------------------

    public function test_unlink_removes_symmetric_relationship(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        Persona::for($userA)->linkTo($userB, 'friend');
        $this->assertEquals(1, Relationship::count());

        $deleted = Persona::for($userB)->unlinkFrom($userA, 'friend');
        $this->assertTrue($deleted);
        $this->assertEquals(0, Relationship::count());
    }

    public function test_unlink_returns_false_when_not_found(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        $deleted = Persona::for($userA)->unlinkFrom($userB, 'friend');
        $this->assertFalse($deleted);
    }

    // -------------------------------------------------------------------------
    // Relationship type validation
    // -------------------------------------------------------------------------

    public function test_invalid_relationship_type_throws(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not allowed');

        Persona::for($userA)->linkTo($userB, 'boss_of');
    }

    // -------------------------------------------------------------------------
    // getFor retrieval
    // -------------------------------------------------------------------------

    public function test_get_for_returns_related_entities(): void
    {
        $userA = $this->createUser(1);
        $userB = $this->createUser(2);

        Persona::for($userA)->linkTo($userB, 'friend');

        $relationships = Persona::manager()->relationships()->getFor($userA);

        $this->assertCount(1, $relationships);
        $this->assertEquals('friend', $relationships->first()->type);
    }
}