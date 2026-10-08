<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('visitor_id')->index();
            $table->string('event', 40);
            $table->string('path', 255)->nullable();
            $table->string('referrer', 255)->nullable();
            $table->json('utm')->nullable();
            $table->json('props')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['event', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('analytics_events'); }
};
