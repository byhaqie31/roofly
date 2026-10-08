<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Owner payout accounts (spec 2026-10-08 payout-accounts-duitnow § 3.1). */
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_id');
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();

            $table->string('label', 60);
            $table->string('bank', 40);                       // App\Enums\MalaysianBank slug
            $table->string('account_holder_name', 120);
            $table->string('account_number', 30)->nullable(); // digits only
            $table->string('duitnow_id_type', 20)->nullable(); // phone|mykad|brn|passport
            $table->string('duitnow_id', 40)->nullable();
            $table->boolean('is_default')->default(false);    // exactly one per owner (enforced in the controller)

            $table->timestamps();

            $table->index(['owner_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_accounts');
    }
};
