<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_id');
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();

            // Identity
            $table->string('name');
            $table->string('internal_label')->nullable();
            $table->enum('type', ['condo', 'landed', 'shoplot', 'room']);
            $table->text('notes')->nullable();

            // Location
            $table->string('address');
            $table->string('city');
            $table->enum('state', [
                'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan',
                'Pahang', 'Perak', 'Perlis', 'Pulau Pinang', 'Sabah',
                'Sarawak', 'Selangor', 'Terengganu',
                'W.P. Kuala Lumpur', 'W.P. Labuan', 'W.P. Putrajaya',
            ]);
            $table->string('postcode', 5);

            // Specifications
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->unsignedInteger('built_up_sqft')->nullable();
            $table->unsignedInteger('land_sqft')->nullable();
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->unsignedTinyInteger('parking_lots')->nullable();
            $table->enum('furnishing', ['unfurnished', 'partial', 'fully'])->nullable();

            // JSON sub-objects — one per detail-page tab, flexible while fields stabilise
            $table->json('ownership')->nullable();   // title info, acquisition, valuation, mortgage
            $table->json('utilities')->nullable();   // recurring fees + service account numbers

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
