<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // rental | own_stay | investment — string (not DB enum) so sqlite ALTERs cleanly.
            $table->string('purpose', 20)->default('rental')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
