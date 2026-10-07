<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Messages sent from the in-app help button (owners + tenants): admin → Enquiries → Messages.
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('role', 20)->nullable();          // owner | tenant at send time
            $table->string('type', 20);                      // issue | feedback | question
            $table->text('message');
            $table->string('page_url', 500)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('status', 20)->default('new');   // new | replied | closed
            $table->text('admin_note')->nullable();
            $table->foreignUuid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
