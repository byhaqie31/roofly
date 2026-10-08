<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tenant lifecycle — null for owners/admins. String (not DB enum)
            // so sqlite ALTERs cleanly; values enforced by FormRequests.
            $table->string('status', 20)->nullable()->after('invited_at');
            // Owner who invited this tenant — links pre-agreement tenants
            // to their owner so they appear in GET /tenants.
            $table->foreignUuid('invited_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn('status');
        });
    }
};
