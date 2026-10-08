<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTenantColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_status_and_inviter_are_persisted(): void
    {
        $owner  = User::factory()->owner()->create();
        $tenant = User::factory()->tenant()->create([
            'status'     => 'notice_given',
            'invited_by' => $owner->id,
        ]);

        $this->assertSame('notice_given', $tenant->fresh()->status);
        $this->assertSame($owner->id, $tenant->fresh()->invited_by);
        $this->assertNull($owner->fresh()->status);
    }
}
