<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** null = "use the owner's default payout account" (spec 2026-10-08 § 3.2). Not a term column. */
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->foreignUuid('payout_account_id')->nullable()->after('review_note')
                ->constrained('payout_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_account_id');
        });
    }
};
