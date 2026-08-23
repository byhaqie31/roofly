<?php

namespace Tests\Feature;

use App\Models\Agreement;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_world_matches_mock_counts_and_anchors(): void
    {
        $this->seed(DemoSeeder::class);

        // Exact counts ported from frontend/app/mocks/*.ts.
        $this->assertSame(9, User::where('role', 'owner')->count());
        $this->assertSame(5, User::where('role', 'tenant')->count()); // tenants.ts: 5 records
        $this->assertTrue(User::where('email', 'like', '%aminah%')->orWhere('name', 'like', '%Aminah%')->exists());
        $this->assertSame(2, User::where('role', 'admin')->count());
        $super = User::where('email', 'admin@roofly.my')->first();
        $this->assertTrue($super->is_super_admin);
        $ops = User::where('email', 'ops@roofly.my')->first();
        $this->assertFalse($ops->is_super_admin);
        $this->assertTrue($ops->hasPermissionTo('owners.suspend'));
        $this->assertFalse($ops->hasPermissionTo('admins.manage'));
        $this->assertTrue($ops->hasPermissionTo('analytics.view'));
        $this->assertSame(14, \Spatie\Permission\Models\Permission::count());
        $this->assertSame(5, Property::count());   // properties.ts: 5 records
        $this->assertSame(8, Unit::count());        // units.ts: 8 records
        $this->assertSame(7, PropertyCoOwner::count()); // properties.ts coOwners across all 5 properties
        $this->assertSame(4, Agreement::count());   // agreements.ts: 4 records
        $this->assertSame(7, Ticket::count());       // tickets.ts: 7 records
        $this->assertSame(7, TicketComment::count()); // tickets.ts: 7 comments

        // Invoices/payments are generated relative to "today" by the frontend mock
        // (invoices.ts), so counts are not fixed — assert they exist instead.
        $this->assertGreaterThan(0, Invoice::count());

        // Analytics demo data: 90 days of deterministic marketing-site events + waitlist leads.
        $this->assertGreaterThan(1000, \App\Models\AnalyticsEvent::count());
        $this->assertSame(40, \App\Models\Lead::count());
        $this->assertSame(8, \App\Models\Lead::whereNotNull('converted_user_id')->count());

        // Anchor: Aminah has an active agreement (tenant shell depends on it)
        $aminah = User::where('name', 'like', '%Aminah%')->where('role', 'tenant')->first();
        $this->assertNotNull($aminah);
        $this->assertTrue(Agreement::where('tenant_id', $aminah->id)->where('status', 'active')->exists());

        // Anchor: a notice_given tenant exists (dashboard needs-attention feed)
        $this->assertTrue(User::where('status', 'notice_given')->exists());

        // Idempotent-ish: reseeding must not crash on unique constraints
        $this->seed(DemoSeeder::class);

        // Counts should be stable across a reseed for non-date-relative entities.
        $this->assertSame(5, Property::count());
        $this->assertSame(4, Agreement::count());
        $this->assertSame(7, Ticket::count());
        $this->assertSame(40, \App\Models\Lead::count());
    }
}
