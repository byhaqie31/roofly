<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Separate relational table (not JSON) — DB-enforced sum=100 and exactly-one primary
        // invariants are managed in the Property repository / service layer.
        // See ADR-004 and MOCK-POC § 4.7.
        Schema::create('property_co_owners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();

            // nullable: allows off-platform co-owners who don't have a Roofly account
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('name');                          // display name (mirrors user.name for platform users)
            $table->decimal('share_pct', 5, 2);             // 0.00–100.00; sum per property must = 100
            $table->boolean('is_primary')->default(false);   // exactly one true per property

            $table->timestamps();

            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_co_owners');
    }
};
