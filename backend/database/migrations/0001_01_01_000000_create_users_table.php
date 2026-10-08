<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable();
            $table->enum('role', ['owner', 'tenant', 'admin'])->default('owner');
            $table->string('password')->nullable(); // nullable: magic-link tenants set password on first login
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->rememberToken();

            // Owner-specific fields
            $table->string('business_name')->nullable();
            $table->string('bank_account_last4', 4)->nullable();
            $table->string('photo_path')->nullable(); // Phase 4 — file storage
            $table->enum('plan_tier', ['free', 'starter', 'pro', 'business'])->default('free');
            $table->json('owner_preferences')->nullable();       // { locale, theme, money_locale }
            $table->json('notification_preferences')->nullable(); // { events: {...}, channels: {...} }

            // Tenant-specific fields (JSON — stabilising before column promotion)
            $table->json('personal_info')->nullable();       // ic_number, date_of_birth, occupation, employer, monthly_income_cents, nationality
            $table->json('emergency_contact')->nullable();   // name, phone, relationship

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
