<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Raw audit trail of all Billplz callbacks. See ADR-012.
        // Store everything — process and mark processed_at. Critical for payment disputes.
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('processed_at'); // find unprocessed webhooks efficiently
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};
