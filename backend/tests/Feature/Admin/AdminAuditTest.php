<?php
// backend/tests/Feature/Admin/AdminAuditTest.php
namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;
    private User $ops;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminPermissionSeeder::class);
        $this->auditor = User::factory()->admin()->create();
        $this->auditor->givePermissionTo(AdminPermissions::AUDIT_VIEW);
        $this->ops = User::factory()->admin()->create();
        $this->owner = User::factory()->owner()->create();

        $this->actingAs($this->ops);
        app(AuditLogger::class)->record(AuditLogger::OWNER_WARNED, $this->owner, [], ['template' => 'payment_overdue']);
        $this->actingAs($this->auditor);
        app(AuditLogger::class)->record(AuditLogger::ADMIN_LOGIN, $this->auditor);
    }

    public function test_audit_view_sees_all_with_filters(): void
    {
        Sanctum::actingAs($this->auditor);
        $res = $this->getJson('/api/admin/audit')->assertOk();
        $this->assertSame(2, $res->json('meta.total'));
        $this->assertSame('admin.login', $res->json('data.0.action'));

        $this->assertSame(1, $this->getJson("/api/admin/audit?actorId={$this->ops->id}")->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/audit?action=owner.warned')->json('meta.total'));
        $this->assertSame(1, $this->getJson("/api/admin/audit?subjectType=user&subjectId={$this->owner->id}")->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/admin/audit?to=2000-01-01')->json('meta.total'));
        $this->assertSame(2, $this->getJson('/api/admin/audit?from=2000-01-01')->json('meta.total'));
    }

    public function test_without_audit_view_only_own_entries(): void
    {
        Sanctum::actingAs($this->ops);
        $res = $this->getJson('/api/admin/audit')->assertOk();
        $this->assertSame(1, $res->json('meta.total'));
        $this->assertSame($this->ops->id, $res->json('data.0.actorId'));
        // Filtering for someone else still returns nothing.
        $this->assertSame(0, $this->getJson("/api/admin/audit?actorId={$this->auditor->id}")->json('meta.total'));
    }

    public function test_csv_export_requires_audit_view(): void
    {
        Sanctum::actingAs($this->ops);
        $this->get('/api/admin/audit/export.csv')->assertForbidden();

        Sanctum::actingAs($this->auditor);
        $res = $this->get('/api/admin/audit/export.csv')->assertOk();
        $this->assertStringStartsWith('text/csv', $res->headers->get('content-type'));
        $lines = explode("\n", trim($res->streamedContent()));
        $this->assertSame('id,createdAt,action,actorName,subjectType,subjectId,subjectName,reason,before,after', $lines[0]);
        $this->assertCount(3, $lines);
    }
}
