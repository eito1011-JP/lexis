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
            $table->foreign(['pull_request_edit_session_id'], 'fk_pr_edit_session_diffs_session_id')->references(['id'])->on('pull_request_edit_sessions')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pull_request_edit_session_diffs', function (Blueprint $table) {
            $table->dropForeign('fk_pr_edit_session_diffs_session_id');
        });
    }
};
