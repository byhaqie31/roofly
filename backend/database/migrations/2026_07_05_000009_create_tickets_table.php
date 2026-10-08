<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('unit_id');
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();

            $table->uuid('reporter_id');
            $table->foreign('reporter_id')->references('id')->on('users')->cascadeOnDelete();
            $table->enum('reporter_role', ['owner', 'tenant']);

            $table->enum('category', ['plumbing', 'electrical', 'appliance', 'structural', 'pest', 'other']);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent']);
            $table->string('title', 100);
            $table->text('description');
            $table->enum('status', ['new', 'in_progress', 'resolved', 'reopened'])->default('new');
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('unit_id');
            $table->index('reporter_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
