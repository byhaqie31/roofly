<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tenant onboarding (spec 2026-10-07 § 4.4): an invited tenant who just set a
 * password completes the core profile fields before seeing /tenant. Mandatory,
 * core fields required (owner's decision 2026-10-07).
 */
class TenantOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function body(array $overrides = []): array
    {
        return array_replace_recursive([
            'name'  => 'Adi Putra',
            'phone' => '+60 12-345 6789',
            'personal' => ['icNumber' => '880314-14-5687', 'nationality' => 'Malaysian'],
            'emergencyContact' => ['name' => 'Siti', 'phone' => '+60 13-222 3333', 'relationship' => 'Sister'],
        ], $overrides);
    }

    public function test_me_exposes_onboarded_at_for_tenants(): void
    {
        $fresh = User::factory()->tenant()->create(['onboarded_at' => null]);
        Sanctum::actingAs($fresh);
        $this->assertNull($this->getJson('/api/auth/me')->assertOk()->json('onboardedAt'));

        $done = User::factory()->tenant()->create(['onboarded_at' => '2026-10-01 09:00:00']);
        Sanctum::actingAs($done);
        $this->assertSame('2026-10-01T09:00:00.000000Z', $this->getJson('/api/auth/me')->assertOk()->json('onboardedAt'));
    }

    public function test_complete_onboarding_saves_profile_and_stamps_the_tenant(): void
    {
        $tenant = User::factory()->tenant()->create(['onboarded_at' => null, 'phone' => '+60 1']);
        Sanctum::actingAs($tenant);

        $res = $this->patchJson('/api/me/onboarding', $this->body())->assertOk();

        $this->assertSame(AuthContractTest::AUTH_USER_KEYS, array_keys($res->json()));
        $this->assertNotNull($res->json('onboardedAt'));
        $fresh = $tenant->fresh();
        $this->assertSame('+60 12-345 6789', $fresh->phone);
        $this->assertSame('880314-14-5687', $fresh->personal_info['icNumber']);
        $this->assertSame('Siti', $fresh->emergency_contact['name']);
    }

    public function test_core_fields_are_required(): void
    {
        Sanctum::actingAs(User::factory()->tenant()->create(['onboarded_at' => null]));

        $this->patchJson('/api/me/onboarding', $this->body(['personal' => ['icNumber' => '']]))
            ->assertStatus(422)->assertJsonValidationErrors('personal.icNumber');
        $this->patchJson('/api/me/onboarding', $this->body(['personal' => ['icNumber' => '12345']]))
            ->assertStatus(422)->assertJsonValidationErrors('personal.icNumber');
        $this->patchJson('/api/me/onboarding', $this->body(['emergencyContact' => ['phone' => '']]))
            ->assertStatus(422)->assertJsonValidationErrors('emergencyContact.phone');
        $this->patchJson('/api/me/onboarding', $this->body(['emergencyContact' => ['name' => '']]))
            ->assertStatus(422)->assertJsonValidationErrors('emergencyContact.name');
        $this->patchJson('/api/me/onboarding', $this->body(['phone' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_onboarding_is_idempotent_and_tenant_only(): void
    {
        $tenant = User::factory()->tenant()->create(['onboarded_at' => now()->subDay()]);
        Sanctum::actingAs($tenant);
        $first = $tenant->onboarded_at;
        $this->patchJson('/api/me/onboarding', $this->body())->assertOk();
        $this->assertTrue($first->equalTo($tenant->fresh()->onboarded_at));

        Sanctum::actingAs(User::factory()->owner()->create());
        $this->patchJson('/api/me/onboarding', $this->body())->assertForbidden();
    }

    public function test_backfill_marks_existing_tenants_onboarded_but_not_pending_invites(): void
    {
        $active  = User::factory()->tenant()->create(['onboarded_at' => null, 'first_login_at' => '2026-09-01 08:00:00']);
        $noLogin = User::factory()->tenant()->create(['onboarded_at' => null, 'first_login_at' => null]);
        $invited = User::factory()->invitedTenant()->create(['onboarded_at' => null]);
        $owner   = User::factory()->owner()->create(['onboarded_at' => null]);

        $migration = require base_path('database/migrations/2026_10_07_000003_backfill_tenant_onboarded_at.php');
        $migration->up();

        $this->assertSame('2026-09-01 08:00:00', $active->fresh()->onboarded_at->toDateTimeString());
        $this->assertTrue($noLogin->fresh()->onboarded_at->equalTo($noLogin->created_at));
        $this->assertNull($invited->fresh()->onboarded_at);
        $this->assertNull($owner->fresh()->onboarded_at); // owners were handled by their own migration
    }

    public function test_mykad_is_accepted_without_dashes_and_stored_canonically(): void
    {
        $tenant = User::factory()->tenant()->create(['onboarded_at' => null]);
        Sanctum::actingAs($tenant);

        $this->patchJson('/api/me/onboarding', $this->body(['personal' => ['icNumber' => '880314145687']]))->assertOk();

        $this->assertSame('880314-14-5687', $tenant->fresh()->personal_info['icNumber']);
    }

    public function test_date_of_birth_defaults_from_mykad_when_omitted(): void
    {
        $tenant = User::factory()->tenant()->create(['onboarded_at' => null]);
        Sanctum::actingAs($tenant);

        $this->patchJson('/api/me/onboarding', $this->body(['personal' => ['icNumber' => '880314145687']]))->assertOk();
        $this->assertSame('1988-03-14', $tenant->fresh()->personal_info['dateOfBirth']);

        // An explicit date of birth wins over the derived one.
        $other = User::factory()->tenant()->create(['onboarded_at' => null]);
        Sanctum::actingAs($other);
        $this->patchJson('/api/me/onboarding', $this->body(['personal' => ['icNumber' => '880314145687', 'dateOfBirth' => '1988-03-15']]))->assertOk();
        $this->assertSame('1988-03-15', $other->fresh()->personal_info['dateOfBirth']);
    }

    public function test_profile_patch_also_normalizes_mykad(): void
    {
        $tenant = User::factory()->tenant()->create();
        Sanctum::actingAs($tenant);

        $this->patchJson('/api/me/profile', ['personal' => ['icNumber' => '920701081234']])->assertOk();

        $this->assertSame('920701-08-1234', $tenant->fresh()->personal_info['icNumber']);
    }
}
