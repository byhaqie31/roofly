<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PayoutAccount;
use App\Models\User;
use Database\Seeders\LocalSampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalSampleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_a_small_varied_world_and_is_idempotent(): void
    {
        $this->seed(LocalSampleSeeder::class);
        $this->seed(LocalSampleSeeder::class); // deterministic ids — a re-run updates, never duplicates

        $this->assertSame(3, User::where('role', 'owner')->count());
        $this->assertSame(7, User::where('role', 'tenant')->count());
        $this->assertSame(0, User::where('role', 'admin')->count());

        $farah = User::where('email', 'farah@example.com')->firstOrFail();
        $this->assertSame(1, PayoutAccount::where('owner_id', $farah->id)->where('is_default', true)->count());
        $this->assertSame(0, PayoutAccount::where('owner_id', User::where('email', 'daniel@example.com')->value('id'))->count());
        $this->assertNull(User::where('email', 'aisyah@example.com')->value('onboarded_at'));

        $this->assertEqualsCanonicalizing(
            ['active', 'pending_review', 'expired', 'accepted'],
            Agreement::distinct()->pluck('status')->map(fn ($s) => $s->value)->all(),
        );
        $this->assertSame(1, Payment::where('status', 'pending')->count());
        $this->assertTrue(Invoice::where('status', 'overdue')->exists());
        $this->assertSame(Invoice::count(), Invoice::distinct()->count('invoice_number'));
    }

    public function test_refuses_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->app->make(LocalSampleSeeder::class)->run();
            $this->fail('Expected the seeder to refuse in production.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('only runs in local/testing', $e->getMessage());
        }
        $this->assertSame(0, User::count());
    }
}
