<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Agreement review flow (spec 2026-10-07 agreement-review): draft → pending_review → accepted → active. */
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending_review', 'accepted', 'active', 'expired', 'terminated'])
                ->default('draft')->change();
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->timestamp('accepted_at')->nullable()->after('sent_at');
            $table->timestamp('changes_requested_at')->nullable()->after('accepted_at');
            $table->string('review_note', 500)->nullable()->after('changes_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn(['sent_at', 'accepted_at', 'changes_requested_at', 'review_note']);
            $table->enum('status', ['draft', 'active', 'expired', 'terminated'])->default('draft')->change();
        });
    }
};
