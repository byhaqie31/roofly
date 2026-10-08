<?php

namespace Tests\Feature\Admin;

use App\Models\AdminInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdminColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_columns_default_and_cast(): void
    {
        $u = User::factory()->owner()->create();
        $this->assertFalse($u->is_super_admin);
        $this->assertNull($u->suspended_at);
        $this->assertFalse($u->isSuspended());
        $this->assertFalse($u->isDisabled());

        $u->update(['suspended_at' => now(), 'suspension_reason' => 'Unpaid subscription']);
        $this->assertTrue($u->fresh()->isSuspended());
    }

    public function test_factory_states(): void
    {
        $super = User::factory()->superAdmin()->create();
        $this->assertSame('admin', $super->role->value);
        $this->assertTrue($super->is_super_admin);

        $ops = User::factory()->admin()->create();
        $this->assertSame('admin', $ops->role->value);
        $this->assertFalse($ops->is_super_admin);

        $this->assertTrue(User::factory()->owner()->suspended()->create()->isSuspended());
    }

    public function test_admin_invite_usability(): void
    {
        $admin = User::factory()->admin()->create();
        $live = AdminInvite::create(['user_id' => $admin->id, 'token_hash' => hash('sha256', 'a'), 'expires_at' => now()->addDay()]);
        $expired = AdminInvite::create(['user_id' => $admin->id, 'token_hash' => hash('sha256', 'b'), 'expires_at' => now()->subDay()]);
        $used = AdminInvite::create(['user_id' => $admin->id, 'token_hash' => hash('sha256', 'c'), 'expires_at' => now()->addDay(), 'accepted_at' => now()]);

        $this->assertTrue($live->isUsable());
        $this->assertFalse($expired->isUsable());
        $this->assertFalse($used->isUsable());
        $this->assertCount(3, $admin->adminInvites);
    }
}
