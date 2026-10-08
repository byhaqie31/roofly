<?php
// backend/tests/Feature/Admin/AuditLoggerTest.php
namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_writes_admin_log_entry_with_actor_subject_and_properties(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        $this->actingAs($admin);

        $entry = app(AuditLogger::class)->record(
            AuditLogger::OWNER_SUSPENDED,
            $owner,
            ['suspendedAt' => null],
            ['suspendedAt' => '2026-08-23T00:00:00Z'],
            'Unpaid subscription for 2 months',
        );

        $this->assertInstanceOf(Activity::class, $entry);
        $row = Activity::inLog('admin')->latest('id')->first();
        $this->assertSame('owner.suspended', $row->event);
        $this->assertSame($admin->id, $row->causer_id);
        $this->assertSame(User::class, $row->subject_type);
        $this->assertSame($owner->id, $row->subject_id);
        $this->assertSame(['before', 'after', 'reason', 'ip'], array_keys($row->properties->toArray()));
        $this->assertSame('Unpaid subscription for 2 months', $row->properties['reason']);
    }

    public function test_record_without_subject(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        app(AuditLogger::class)->record(AuditLogger::ADMIN_LOGIN);
        $row = Activity::inLog('admin')->latest('id')->first();
        $this->assertSame('admin.login', $row->event);
        $this->assertNull($row->subject_id);
    }
}
