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
        Schema::table('edit_start_versions', function (Blueprint $table) {
            $table->dropColumn('is_deleted');
            $table->dropColumn('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edit_start_versions', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
        });
    }
};
