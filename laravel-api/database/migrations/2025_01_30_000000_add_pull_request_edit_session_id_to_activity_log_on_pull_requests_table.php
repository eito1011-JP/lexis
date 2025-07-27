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
        Schema::table('activity_log_on_pull_requests', function (Blueprint $table) {
            $table->foreignId('pull_request_edit_session_id')->nullable()->constrained('pull_request_edit_sessions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log_on_pull_requests', function (Blueprint $table) {
            $table->dropForeign(['pull_request_edit_session_id']);
            $table->dropColumn('pull_request_edit_session_id');
        });
    }
};
