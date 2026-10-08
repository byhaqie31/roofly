<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();

            $table->string('label');              // e.g. "Unit A", "Master bedroom", "Ground floor shop"
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->unsignedInteger('sqft')->nullable();
            $table->enum('status', ['vacant', 'occupied', 'maintenance'])->default('vacant');

            $table->timestamps();
            $table->softDeletes();

            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
