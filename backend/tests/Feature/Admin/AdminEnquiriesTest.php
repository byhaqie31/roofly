<?php

namespace Tests\Feature\Admin;

use App\Models\Enquiry;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AdminPermissions;
use Database\Seeders\AdminPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminEnquiriesTest extends TestCase
{
    use RefreshDatabase;

    public const ENQUIRY_KEYS = ['id', 'type', 'status', 'message', 'pageUrl', 'name', 'email', 'role', 'userId', 'adminNote', 'handledByName', 'statusChangedAt', 'createdAt'];

    private function actingAsAdmin(array $permissions = [AdminPermissions::SUPPORT_MANAGE]): User
    {
        $this->seed(AdminPermissionSeeder::class);
        $admin = User::factory()->admin()->create(['name' => 'Ops One']);
        $admin->givePermissionTo($permissions);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_list_filters_and_resource_shape(): void
    {
        $this->actingAsAdmin();
        Enquiry::factory()->create(['type' => 'issue', 'status' => 'new', 'message' => 'Invoice PDF is blank']);
        Enquiry::factory()->create(['type' => 'feedback', 'status' => 'closed', 'email' => 'fan@x.my']);

        $res = $this->getJson('/api/admin/enquiries')->assertOk();
        $this->assertSame(2, $res->json('meta.total'));
        $this->assertSame(1, $res->json('meta.newCount'));
        $this->assertSame(self::ENQUIRY_KEYS, array_keys($res->json('data.0')));

        $this->assertSame(1, $this->getJson('/api/admin/enquiries?status=new')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/enquiries?type=feedback')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/enquiries?q=blank')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/enquiries?q=fan@')->json('meta.total'));
        $this->getJson('/api/admin/enquiries?status=bogus')->assertUnprocessable();
    }

    public function test_update_status_and_note_records_handler_and_audit(): void
    {
        $admin = $this->actingAsAdmin();
        $e = Enquiry::factory()->create();

        $res = $this->patchJson("/api/admin/enquiries/{$e->id}", ['status' => 'replied', 'adminNote' => 'Emailed a fix.'])->assertOk();

        $this->assertSame('replied', $res->json('status'));
        $this->assertSame('Emailed a fix.', $res->json('adminNote'));
        $this->assertSame('Ops One', $res->json('handledByName'));
        $this->assertNotNull($res->json('statusChangedAt'));

        $log = Activity::latest('id')->first();
        $this->assertSame(AuditLogger::ENQUIRY_UPDATED, $log->event);
        $this->assertSame($admin->id, $log->causer_id);
        $this->assertSame('new', $log->properties['before']['status']);
    }

    public function test_note_only_update_keeps_status_timestamp(): void
    {
        $this->actingAsAdmin();
        $e = Enquiry::factory()->create();

        $this->patchJson("/api/admin/enquiries/{$e->id}", ['adminNote' => 'Looking into it'])->assertOk()
            ->assertJsonPath('status', 'new')->assertJsonPath('statusChangedAt', null);
    }

    public function test_requires_support_manage(): void
    {
        $this->actingAsAdmin([AdminPermissions::ANALYTICS_VIEW]);
        $e = Enquiry::factory()->create();

        $this->getJson('/api/admin/enquiries')->assertForbidden();
        $this->patchJson("/api/admin/enquiries/{$e->id}", ['status' => 'closed'])->assertForbidden();
    }
}
