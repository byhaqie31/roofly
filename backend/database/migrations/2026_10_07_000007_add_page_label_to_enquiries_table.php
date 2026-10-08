<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Human page name captured by the help button ("Owner app · Payments"), shown next to page_url.
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('page_label', 120)->nullable()->after('page_url');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn('page_label');
        });
    }
};
