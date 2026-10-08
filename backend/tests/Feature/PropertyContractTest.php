<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        Sanctum::actingAs($this->owner);
    }

    public function test_index_returns_bare_camel_case_array(): void
    {
        $p = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $p->id, 'user_id' => $this->owner->id]);

        $res = $this->getJson('/api/properties')->assertOk();
        $this->assertIsList($res->json());           // bare array, no {data:…}
        $row = $res->json()[0];
        $this->assertArrayHasKey('ownerId', $row);
        $this->assertArrayHasKey('coOwners', $row);
        $this->assertArrayNotHasKey('owner_id', $row);
    }

    public function test_store_accepts_camel_case_tier1_input(): void
    {
        $res = $this->postJson('/api/properties', [
            'name' => 'Vista Residence', 'type' => 'condo',
            'address' => '12 Jalan Ampang', 'city' => 'Kuala Lumpur',
            'state' => 'W.P. Kuala Lumpur', 'postcode' => '50450',
        ])->assertCreated();
        $this->assertSame('Vista Residence', $res->json('name'));
        // Auto-seeded primary co-owner
        $this->assertCount(1, $res->json('coOwners'));
        $this->assertTrue($res->json('coOwners.0.isPrimary'));
    }

    public function test_update_accepts_camel_case_tier2_fields_and_blobs(): void
    {
        $p = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $p->id, 'user_id' => $this->owner->id]);

        $res = $this->patchJson("/api/properties/{$p->id}", [
            'builtUpSqft' => 1200,
            'parkingLots' => 2,
            'ownership'   => ['titleType' => 'leasehold', 'purchasePrice' => 45000000],
        ])->assertOk();
        $this->assertSame(1200, $res->json('builtUpSqft'));
        $this->assertSame('leasehold', $res->json('ownership.titleType'));
        $this->assertSame(45000000, $p->fresh()->ownership['purchasePrice']); // stored verbatim
    }

    public function test_co_owner_sync_enforces_invariants(): void
    {
        $p = Property::factory()->create(['owner_id' => $this->owner->id]);

        // sum != 100 rejected
        $this->putJson("/api/properties/{$p->id}/co-owners", [
            'coOwners' => [['name' => 'A', 'sharePct' => 60, 'isPrimary' => true]],
        ])->assertStatus(422);

        // valid sync
        $res = $this->putJson("/api/properties/{$p->id}/co-owners", [
            'coOwners' => [
                ['name' => 'A', 'sharePct' => 60, 'isPrimary' => true],
                ['name' => 'B', 'sharePct' => 40, 'isPrimary' => false],
            ],
        ])->assertOk();
        $this->assertSame(['id', 'name', 'sharePct', 'isPrimary'], array_keys($res->json()[0]));
    }

    public function test_other_owner_cannot_see_my_property(): void
    {
        $p = Property::factory()->create(['owner_id' => $this->owner->id]);
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->getJson("/api/properties/{$p->id}")->assertForbidden();
    }

    public function test_update_syncs_co_owners_when_present(): void
    {
        $p = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $p->id, 'user_id' => $this->owner->id]);

        $res = $this->patchJson("/api/properties/{$p->id}", [
            'internalLabel' => 'Block A',
            'coOwners' => [
                ['name' => 'A', 'sharePct' => 70, 'isPrimary' => true],
                ['name' => 'B', 'sharePct' => 30, 'isPrimary' => false],
            ],
        ])->assertOk();

        $this->assertSame('Block A', $res->json('internalLabel'));
        $this->assertCount(2, $res->json('coOwners'));
        // assertEquals, not assertSame: whole-number floats collapse to ints on JSON
        // round-trip (PHP serialize_precision=-1), unrelated to the sync logic under test.
        $this->assertEquals(70.0, $res->json('coOwners.0.sharePct'));
        $this->assertCount(2, $p->fresh()->coOwners);
    }

    public function test_update_rejects_invalid_co_owner_invariants(): void
    {
        $p = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $p->id, 'user_id' => $this->owner->id]);

        $this->patchJson("/api/properties/{$p->id}", [
            'coOwners' => [['name' => 'A', 'sharePct' => 60, 'isPrimary' => true]],
        ])->assertStatus(422);

        $this->assertCount(1, $p->fresh()->coOwners); // untouched
    }

    public function test_update_without_co_owners_leaves_them_untouched(): void
    {
        $p = Property::factory()->create(['owner_id' => $this->owner->id]);
        PropertyCoOwner::factory()->create(['property_id' => $p->id, 'user_id' => $this->owner->id]);

        $this->patchJson("/api/properties/{$p->id}", ['notes' => 'hello'])->assertOk();
        $this->assertCount(1, $p->fresh()->coOwners);
    }
}
