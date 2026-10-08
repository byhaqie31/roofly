<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payments settle invoices. One invoice → one payment normally, but supports
        // retries and failures. Every attempt is logged. See ADR-007.
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();

            $table->unsignedBigInteger('amount_cents');
            $table->enum('method', ['fpx', 'card', 'cash', 'transfer']);
            $table->string('billplz_bill_id')->nullable(); // null for cash/manual payments
            $table->string('reference')->nullable();       // owner-entered reference for cash/transfer
            $table->enum('status', ['pending', 'successful', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
