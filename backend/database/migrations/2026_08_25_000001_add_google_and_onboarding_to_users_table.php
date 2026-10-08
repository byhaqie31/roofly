<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar_url', 500)->nullable()->after('google_id');
            // Owner onboarding (spec 2026-08-23 google-login-owner-onboarding § 4).
            $table->json('purposes')->nullable()->after('notification_preferences');
            $table->timestamp('onboarded_at')->nullable()->after('purposes');
            $table->timestamp('checklist_dismissed_at')->nullable()->after('onboarded_at');
        });

        // Existing owners are never ambushed by the onboarding screen.
        DB::table('users')->where('role', 'owner')->whereNull('onboarded_at')
            ->update(['onboarded_at' => DB::raw('created_at'), 'purposes' => json_encode(['rental'])]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'avatar_url', 'purposes', 'onboarded_at', 'checklist_dismissed_at']);
        });
    }
};
