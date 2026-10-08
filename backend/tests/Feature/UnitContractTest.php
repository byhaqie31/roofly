<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitContractTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        $this->property = Property::factory()->create(['owner_id' => $this->owner->id]);
        Sanctum::actingAs($this->owner);
    }

    public function test_flat_index_lists_all_owner_units_camel_case(): void
    {
        Unit::factory()->create(['property_id' => $this->property->id]);
        Unit::factory()->create(); // another owner's unit — must not appear

        $res = $this->getJson('/api/units')->assertOk();
        $this->assertCount(1, $res->json());
        $this->assertSame(
            ['id', 'propertyId', 'label', 'bedrooms', 'bathrooms', 'sqft', 'status', 'createdAt'],
            array_keys($res->json()[0])
        );
    }

    public function test_flat_update_and_delete(): void
    {
        $unit = Unit::factory()->create(['property_id' => $this->property->id]);

        $this->patchJson("/api/units/{$unit->id}", ['status' => 'occupied'])
            ->assertOk()->assertJsonPath('status', 'occupied');
        $this->deleteJson("/api/units/{$unit->id}")->assertNoContent();
    }

    public function test_nested_create(): void
    {
        $this->postJson("/api/properties/{$this->property->id}/units", [
            'label' => 'A-12-3', 'bedrooms' => 3, 'status' => 'vacant',
        ])->assertCreated()->assertJsonPath('label', 'A-12-3');
    }

    public function test_flat_routes_block_other_owners(): void
    {
        $unit = Unit::factory()->create(['property_id' => $this->property->id]);
        Sanctum::actingAs(User::factory()->owner()->create());
        $this->getJson("/api/units/{$unit->id}")->assertForbidden();
    }
}
