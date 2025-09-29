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
        Schema::table('user_branches', function (Blueprint $table) {
            $table->dropColumn('snapshot_commit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_branches', function (Blueprint $table) {
            $table->string('snapshot_commit');
        });
    }
};
