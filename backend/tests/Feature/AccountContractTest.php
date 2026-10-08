<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->owner()->create());
    }

    public function test_show_returns_owner_account_envelope_with_defaults(): void
    {
        $res = $this->getJson('/api/account')->assertOk();
        $this->assertSame(['profile', 'preferences', 'notifications', 'planTier'], array_keys($res->json()));
        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'photoUrl', 'businessName'], // bankAccountLast4 dropped → payout accounts (spec 2026-10-08)
            array_keys($res->json('profile'))
        );
        $this->assertSame(['locale' => 'en', 'theme' => 'system', 'moneyLocale' => 'en-MY'], $res->json('preferences'));
        $this->assertSame('free', $res->json('planTier'));
    }

    public function test_patch_profile_returns_full_envelope(): void
    {
        $res = $this->patchJson('/api/account/profile', ['businessName' => 'Roofly Homes'])->assertOk();
        $this->assertSame('Roofly Homes', $res->json('profile.businessName'));
        $this->assertSame(['profile', 'preferences', 'notifications', 'planTier'], array_keys($res->json()));
    }

    public function test_patch_preferences_merges(): void
    {
        $this->patchJson('/api/account/preferences', ['theme' => 'dark'])->assertOk();
        $res = $this->getJson('/api/account');
        $this->assertSame('dark', $res->json('preferences.theme'));
        $this->assertSame('en', $res->json('preferences.locale')); // untouched default kept
    }

    public function test_plans_camel_case(): void
    {
        $res = $this->getJson('/api/plans')->assertOk();
        $this->assertSame(['tier', 'priceRm', 'unitsCap', 'description'], array_keys($res->json()[0]));
        $this->assertSame('unlimited', $res->json('3.unitsCap'));
    }
}
