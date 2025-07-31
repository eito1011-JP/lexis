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
        Schema::table('pull_request_edit_session_diffs', function (Blueprint $table) {
            $table->enum('diff_type', ['created', 'updated', 'deleted'])->nullable()->after('target_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pull_request_edit_session_diffs', function (Blueprint $table) {
            $table->dropColumn('diff_type');
        });
    }
};
