<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tenant transfer claims → owner confirm/reject (spec 2026-10-08 § 3.3). */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignUuid('payout_account_id')->nullable()->after('invoice_id')
                ->constrained('payout_accounts')->nullOnDelete();
            $table->string('note', 500)->nullable()->after('reference');
            $table->string('rejection_reason', 300)->nullable()->after('note');
            $table->timestamp('confirmed_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_account_id');
            $table->dropColumn(['note', 'rejection_reason', 'confirmed_at']);
        });
    }
};
