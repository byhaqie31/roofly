<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PropertyPurposeTest extends TestCase
{
    use RefreshDatabase;

    private array $tier1 = [
        'name' => 'Home', 'type' => 'landed', 'address' => '1 Jalan Satu',
        'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40000',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->owner()->create());
    }

    public function test_store_accepts_purpose_and_defaults_to_rental(): void
    {
        $res = $this->postJson('/api/properties', $this->tier1 + ['purpose' => 'own_stay'])->assertCreated();
        $this->assertSame('own_stay', $res->json('purpose'));

        $res = $this->postJson('/api/properties', ['name' => 'Two'] + $this->tier1)->assertCreated();
        $this->assertSame('rental', $res->json('purpose'));
    }

    public function test_store_rejects_unknown_purpose(): void
    {
        $this->postJson('/api/properties', $this->tier1 + ['purpose' => 'hotel'])->assertStatus(422);
    }

    public function test_update_changes_purpose(): void
    {
        $p = Property::factory()->create(['owner_id' => auth()->id()]);
        $res = $this->patchJson("/api/properties/{$p->id}", ['purpose' => 'investment'])->assertOk();
        $this->assertSame('investment', $res->json('purpose'));
        $this->assertSame('investment', $p->fresh()->purpose->value);
    }

    public function test_rental_scope(): void
    {
        Property::factory()->create(['owner_id' => auth()->id(), 'purpose' => 'own_stay']);
        Property::factory()->create(['owner_id' => auth()->id()]);
        $this->assertSame(1, Property::rental()->count());
    }
}
