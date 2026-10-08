<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agreement = the legal contract. See ADR-005.
        // Tenancy (actual occupation record) is a separate table.
        Schema::create('agreements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('unit_id');
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();

            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('users')->cascadeOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            // All money in cents (sen). See ADR-002 and useMoney.ts.
            $table->unsignedBigInteger('rent_amount_cents');
            $table->unsignedBigInteger('deposit_amount_cents');
            $table->unsignedBigInteger('late_fee_cents')->default(0);

            $table->unsignedTinyInteger('rent_due_day'); // 1–28; avoids month-end edge cases

            $table->enum('status', ['draft', 'active', 'expired', 'terminated'])->default('draft');

            $table->timestamps();
            $table->softDeletes();

            $table->index('unit_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};
