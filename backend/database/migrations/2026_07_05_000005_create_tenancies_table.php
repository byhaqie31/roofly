<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenancy = actual occupation record. Agreement = legal document. See ADR-005.
        // Renewals create a new agreement but may share the same tenancy if the tenant
        // didn't physically move out between terms.
        Schema::create('tenancies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agreement_id');
            $table->foreign('agreement_id')->references('id')->on('agreements')->cascadeOnDelete();

            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('users')->cascadeOnDelete();

            $table->timestamp('moved_in_at')->nullable();
            $table->timestamp('moved_out_at')->nullable();

            $table->timestamps();

            $table->index('agreement_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenancies');
    }
};
