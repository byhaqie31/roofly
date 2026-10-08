<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One invoice per rent period per agreement. Makes InvoiceGenerator (the
     * controller hooks + the daily `invoices:roll`) safe to run twice. Soft-
     * deleted rows still occupy the slot on purpose — a period is cancelled
     * (status), never deleted and re-created.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['agreement_id', 'due_date'], 'invoices_agreement_due_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_agreement_due_date_unique');
        });
    }
};
