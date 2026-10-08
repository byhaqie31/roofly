<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\PayoutAccount;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Spec: docs/superpowers/specs/2026-10-08-payout-accounts-duitnow-design.md § 3.1, § 3.2, § 4. */
class PayoutAccountTest extends TestCase
{
    use RefreshDatabase;

    public const KEYS = ['id', 'label', 'bank', 'accountHolderName', 'accountNumber', 'duitnowIdType', 'duitnowId', 'isDefault', 'agreementCount', 'createdAt'];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->owner()->create();
        Sanctum::actingAs($this->owner);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'label'             => 'Personal Maybank',
            'bank'              => 'maybank',
            'accountHolderName' => 'Cik Aminah',
            'accountNumber'     => '5140 1234-4521',
            'duitnowIdType'     => 'phone',
            'duitnowId'         => '+60123456789',
        ], $overrides);
    }

    private function account(array $attrs = []): PayoutAccount
    {
        return PayoutAccount::factory()->create(array_merge(['owner_id' => $this->owner->id], $attrs));
    }

    private function agreementFor(User $owner, array $attrs = []): Agreement
    {
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $unit = Unit::factory()->create(['property_id' => $property->id]);

        return Agreement::factory()->create(array_merge(['unit_id' => $unit->id], $attrs));
    }

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function test_first_account_is_default_and_number_is_normalised(): void
    {
        $res = $this->postJson('/api/payout-accounts', $this->payload(['isDefault' => false]))->assertCreated();

        $this->assertSame(self::KEYS, array_keys($res->json()));
        $this->assertTrue($res->json('isDefault'));
        $this->assertSame('514012344521', $res->json('accountNumber'));
        $this->assertSame(0, $res->json('agreementCount'));
    }

    public function test_second_account_is_not_default_unless_asked(): void
    {
        $first = $this->account(['is_default' => true]);

        $second = $this->postJson('/api/payout-accounts', $this->payload(['label' => 'CIMB']))->assertCreated();
        $this->assertFalse($second->json('isDefault'));

        $third = $this->postJson('/api/payout-accounts', $this->payload(['label' => 'RHB', 'isDefault' => true]))->assertCreated();
        $this->assertTrue($third->json('isDefault'));
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, PayoutAccount::where('owner_id', $this->owner->id)->where('is_default', true)->count());
    }

    public function test_index_lists_default_first_then_oldest_with_agreement_counts(): void
    {
        $old = $this->account(['label' => 'Old', 'created_at' => now()->subDays(3)]);
        $default = $this->account(['label' => 'Default', 'is_default' => true, 'created_at' => now()->subDay()]);
        $mid = $this->account(['label' => 'Mid', 'created_at' => now()->subDays(2)]);
        $this->account(['owner_id' => User::factory()->owner()->create()->id]); // someone else's
        $this->agreementFor($this->owner, ['payout_account_id' => $mid->id]);
        $this->agreementFor($this->owner, ['payout_account_id' => $mid->id]);

        $res = $this->getJson('/api/payout-accounts')->assertOk();

        $this->assertSame([$default->id, $old->id, $mid->id], array_column($res->json(), 'id'));
        $this->assertSame([0, 0, 2], array_column($res->json(), 'agreementCount'));
    }

    public function test_update_patches_fields(): void
    {
        $a = $this->account(['is_default' => true]);

        $res = $this->patchJson("/api/payout-accounts/{$a->id}", ['label' => 'Renamed', 'accountNumber' => '1234-5678'])->assertOk();

        $this->assertSame('Renamed', $res->json('label'));
        $this->assertSame('12345678', $res->json('accountNumber'));
        $this->assertSame(self::KEYS, array_keys($res->json()));
    }

    public function test_set_default_flips_the_rest_and_returns_the_list(): void
    {
        $a = $this->account(['is_default' => true]);
        $b = $this->account();

        $res = $this->postJson("/api/payout-accounts/{$b->id}/default")->assertOk();

        $this->assertSame($b->id, $res->json('0.id'));
        $this->assertTrue($res->json('0.isDefault'));
        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
    }

    public function test_deleting_the_default_promotes_the_oldest_remaining(): void
    {
        $default = $this->account(['is_default' => true, 'created_at' => now()->subDays(5)]);
        $newer = $this->account(['created_at' => now()->subDay()]);
        $oldest = $this->account(['created_at' => now()->subDays(3)]);

        $this->deleteJson("/api/payout-accounts/{$default->id}")->assertNoContent();

        $this->assertNull(PayoutAccount::find($default->id));
        $this->assertTrue($oldest->fresh()->is_default);
        $this->assertFalse($newer->fresh()->is_default);
    }

    public function test_deleting_an_account_nulls_agreements_that_used_it(): void
    {
        $default = $this->account(['is_default' => true]);
        $other = $this->account();
        $agreement = $this->agreementFor($this->owner, ['payout_account_id' => $other->id]);

        $this->deleteJson("/api/payout-accounts/{$other->id}")->assertNoContent();

        $this->assertNull($agreement->fresh()->payout_account_id);
        $this->assertSame($default->id, $agreement->fresh()->resolvedPayoutAccount()?->id);
    }

    // ── Validation ──────────────────────────────────────────────────────────

    public function test_needs_an_account_number_or_a_duitnow_id(): void
    {
        $this->postJson('/api/payout-accounts', $this->payload(['accountNumber' => null, 'duitnowIdType' => null, 'duitnowId' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['accountNumber']);

        // DuitNow only is fine
        $this->postJson('/api/payout-accounts', $this->payload(['accountNumber' => null]))->assertCreated();
        // Account number only is fine
        $this->postJson('/api/payout-accounts', $this->payload(['duitnowIdType' => null, 'duitnowId' => null]))->assertCreated();
    }

    public function test_patch_cannot_clear_both_identifiers(): void
    {
        $a = $this->account(['duitnow_id_type' => null, 'duitnow_id' => null]);

        $this->patchJson("/api/payout-accounts/{$a->id}", ['accountNumber' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['accountNumber']);
    }

    public function test_duitnow_id_and_type_come_together(): void
    {
        $this->postJson('/api/payout-accounts', $this->payload(['duitnowIdType' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['duitnowIdType']);
        $this->postJson('/api/payout-accounts', $this->payload(['duitnowId' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['duitnowId']);
    }

    public function test_rejects_unknown_bank_and_id_type_and_non_numeric_account(): void
    {
        $this->postJson('/api/payout-accounts', $this->payload(['bank' => 'bank_of_narnia', 'duitnowIdType' => 'email', 'accountNumber' => 'abc']))
            ->assertUnprocessable()->assertJsonValidationErrors(['bank', 'duitnowIdType', 'accountNumber']);
        $this->postJson('/api/payout-accounts', ['label' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors(['bank', 'accountHolderName']);
    }

    // ── Ownership ───────────────────────────────────────────────────────────

    public function test_another_owners_account_is_forbidden(): void
    {
        $theirs = PayoutAccount::factory()->default()->create();

        $this->patchJson("/api/payout-accounts/{$theirs->id}", ['label' => 'Mine now'])->assertForbidden();
        $this->postJson("/api/payout-accounts/{$theirs->id}/default")->assertForbidden();
        $this->deleteJson("/api/payout-accounts/{$theirs->id}")->assertForbidden();
        $this->assertNotNull($theirs->fresh());
    }

    public function test_tenant_cannot_reach_payout_accounts(): void
    {
        Sanctum::actingAs(User::factory()->tenant()->create());
        $this->getJson('/api/payout-accounts')->assertForbidden();
    }

    // ── Agreements: payoutAccountId (§ 3.2) ─────────────────────────────────

    public function test_agreement_accepts_own_account_and_rejects_others(): void
    {
        $mine = $this->account(['is_default' => true]);
        $theirs = PayoutAccount::factory()->create();
        $agreement = $this->agreementFor($this->owner);

        $this->patchJson("/api/agreements/{$agreement->id}", ['payoutAccountId' => $theirs->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['payoutAccountId']);

        $res = $this->patchJson("/api/agreements/{$agreement->id}", ['payoutAccountId' => $mine->id])->assertOk();
        $this->assertSame($mine->id, $res->json('payoutAccountId'));

        $res = $this->patchJson("/api/agreements/{$agreement->id}", ['payoutAccountId' => null])->assertOk();
        $this->assertNull($res->json('payoutAccountId'));
    }

    public function test_agreement_create_accepts_payout_account_id(): void
    {
        $mine = $this->account(['is_default' => true]);
        $property = Property::factory()->create(['owner_id' => $this->owner->id]);
        $unit = Unit::factory()->create(['property_id' => $property->id]);
        $body = [
            'unitId' => $unit->id, 'tenantId' => User::factory()->tenant()->create()->id,
            'startDate' => '2026-11-01', 'endDate' => '2027-10-31',
            'rentAmount' => 150000, 'depositAmount' => 300000, 'rentDueDay' => 1, 'status' => 'draft',
        ];

        $this->postJson('/api/agreements', $body + ['payoutAccountId' => PayoutAccount::factory()->create()->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['payoutAccountId']);
        $res = $this->postJson('/api/agreements', $body + ['payoutAccountId' => $mine->id])->assertCreated();
        $this->assertSame($mine->id, $res->json('payoutAccountId'));
    }

    public function test_changing_payout_account_does_not_reset_review(): void
    {
        $mine = $this->account(['is_default' => true]);
        $agreement = $this->agreementFor($this->owner, ['status' => 'accepted', 'sent_at' => now(), 'accepted_at' => now()]);

        $res = $this->patchJson("/api/agreements/{$agreement->id}", ['payoutAccountId' => $mine->id])->assertOk();

        $this->assertSame('accepted', $res->json('status'));
    }

    public function test_tenant_sees_the_resolved_account_on_agreement_and_invoices(): void
    {
        $default = $this->account(['is_default' => true, 'label' => 'Default']);
        $override = $this->account(['label' => 'Business']);
        $tenant = User::factory()->tenant()->create();
        $agreement = $this->agreementFor($this->owner, ['tenant_id' => $tenant->id, 'status' => 'active']);
        Invoice::factory()->create(['agreement_id' => $agreement->id]);
        Sanctum::actingAs($tenant);

        // null → owner's default
        $this->assertSame($default->id, $this->getJson('/api/me/agreement?expand=1')->assertOk()->json('payoutAccount.id'));
        $inv = $this->getJson('/api/me/invoices?expand=1')->assertOk();
        $this->assertSame($default->id, $inv->json('0.payoutAccount.id'));
        $this->assertSame(PayoutAccountTest::KEYS, array_keys($inv->json('0.payoutAccount')));
        $this->assertSame($default->account_number, $inv->json('0.payoutAccount.accountNumber'));

        // explicit override wins
        $agreement->update(['payout_account_id' => $override->id]);
        $this->assertSame($override->id, $this->getJson('/api/me/agreement?expand=1')->json('payoutAccount.id'));
        $this->assertSame($override->id, $this->getJson('/api/me/invoices?expand=1')->json('0.payoutAccount.id'));
    }

    public function test_resolved_account_is_null_when_owner_has_none(): void
    {
        $tenant = User::factory()->tenant()->create();
        $agreement = $this->agreementFor($this->owner, ['tenant_id' => $tenant->id, 'status' => 'active']);
        Invoice::factory()->create(['agreement_id' => $agreement->id]);
        Sanctum::actingAs($tenant);

        $this->assertNull($this->getJson('/api/me/agreement?expand=1')->json('payoutAccount'));
        $this->assertNull($this->getJson('/api/me/invoices?expand=1')->json('0.payoutAccount'));
    }

    public function test_owner_account_no_longer_has_bank_account_last4(): void
    {
        $this->assertArrayNotHasKey('bankAccountLast4', $this->getJson('/api/account')->assertOk()->json('profile'));
    }
}
