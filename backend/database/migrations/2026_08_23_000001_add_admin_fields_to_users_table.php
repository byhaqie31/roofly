<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('role');
            $table->timestamp('suspended_at')->nullable()->after('invited_by');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
            $table->timestamp('last_active_at')->nullable()->after('suspension_reason');
            $table->timestamp('first_login_at')->nullable()->after('last_active_at');
            $table->timestamp('disabled_at')->nullable()->after('first_login_at'); // admins only
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_super_admin', 'suspended_at', 'suspension_reason', 'last_active_at', 'first_login_at', 'disabled_at']);
        });
    }
};
