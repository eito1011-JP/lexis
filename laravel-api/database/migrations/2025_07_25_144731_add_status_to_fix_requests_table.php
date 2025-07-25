<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fix_requests', function (Blueprint $table) {
            $table->enum('status', ['pending', 'applied', 'archived'])
                ->default('pending')
                ->after('base_category_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fix_requests', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
