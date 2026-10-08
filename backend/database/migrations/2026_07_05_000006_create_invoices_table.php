<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Invoices are auto-generated from agreements. See ADR-006.
        // One per month per active agreement; cron generates them on day 1.
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agreement_id');
            $table->foreign('agreement_id')->references('id')->on('agreements')->cascadeOnDelete();

            $table->string('invoice_number')->unique(); // INV-0001 … INV-NNNN, chronological
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('late_fee_cents')->default(0);
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->index('agreement_id');
            $table->index(['status', 'due_date']); // dashboard outstanding query
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
