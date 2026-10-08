<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Replaced by payout_accounts (spec 2026-10-08 § 2). Nothing ever wrote it outside the seeder. */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'bank_account_last4')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('bank_account_last4');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('bank_account_last4', 4)->nullable()->after('business_name');
        });
    }
};
