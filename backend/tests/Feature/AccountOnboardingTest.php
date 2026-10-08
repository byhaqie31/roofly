<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_onboarding_sets_purposes_and_timestamp(): void
    {
        $u = User::factory()->owner()->create(['onboarded_at' => null, 'purposes' => null]);
        Sanctum::actingAs($u);
        $res = $this->patchJson('/api/account/onboarding', ['purposes' => ['rental', 'investment']])->assertOk();
        $this->assertSame(['rental', 'investment'], $res->json('purposes'));
        $this->assertNotNull($res->json('onboardedAt'));
        $this->assertSame(AuthContractTest::AUTH_USER_KEYS, array_keys($res->json()));
    }

    public function test_onboarding_is_idempotent_and_validates(): void
    {
        $u = User::factory()->owner()->create(['onboarded_at' => now()->subDay(), 'purposes' => ['rental']]);
        Sanctum::actingAs($u);
        $first = $u->onboarded_at;
        $this->patchJson('/api/account/onboarding', ['purposes' => ['own_stay']])->assertOk();
        $this->assertTrue($first->equalTo($u->fresh()->onboarded_at));
        $this->assertSame(['own_stay'], $u->fresh()->purposes);

        $this->patchJson('/api/account/onboarding', ['purposes' => []])->assertStatus(422);
        $this->patchJson('/api/account/onboarding', ['purposes' => ['hotel']])->assertStatus(422);
    }

    public function test_checklist_dismiss_and_restore(): void
    {
        Sanctum::actingAs($u = User::factory()->owner()->create());
        $this->patchJson('/api/account/checklist', ['dismissed' => true])->assertOk();
        $this->assertNotNull($u->fresh()->checklist_dismissed_at);
        $res = $this->patchJson('/api/account/checklist', ['dismissed' => false])->assertOk();
        $this->assertNull($res->json('checklistDismissedAt'));
    }

    public function test_set_password_only_when_missing(): void
    {
        Sanctum::actingAs($u = User::factory()->owner()->create(['password' => null, 'google_id' => 'g']));
        $res = $this->postJson('/api/account/password', ['password' => 'secret123', 'password_confirmation' => 'secret123'])->assertOk();
        $this->assertTrue($res->json('hasPassword'));
        $this->assertTrue(Hash::check('secret123', $u->fresh()->password));

        $this->postJson('/api/account/password', ['password' => 'another12', 'password_confirmation' => 'another12'])->assertStatus(422);
    }

    public function test_tenant_cannot_hit_owner_account_routes(): void
    {
        Sanctum::actingAs(User::factory()->tenant()->create());
        $this->patchJson('/api/account/onboarding', ['purposes' => ['rental']])->assertForbidden();
    }
}
