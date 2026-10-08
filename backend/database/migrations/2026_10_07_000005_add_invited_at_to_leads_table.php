<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // When an admin last sent this lead the waitlist invitation (Enquiries → Invite).
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('invited_at');
        });
    }
};
