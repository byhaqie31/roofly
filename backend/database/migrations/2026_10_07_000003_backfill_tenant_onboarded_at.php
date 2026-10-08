<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tenant onboarding (spec 2026-10-07 § 4.4) reuses users.onboarded_at. Tenants
     * already using the app must never be ambushed by the new screen, so they are
     * stamped with their first login (or creation). Pending invites stay NULL on
     * purpose: they see onboarding right after setting their password.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'tenant')
            ->whereNull('onboarded_at')
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'invited'))
            ->update(['onboarded_at' => DB::raw('COALESCE(first_login_at, created_at)')]);
    }

    public function down(): void
    {
        // Data back-fill only — nothing structural to undo, and we can't tell
        // back-filled rows from ones onboarded for real.
    }
};
