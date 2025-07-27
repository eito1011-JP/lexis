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
        Schema::table('document_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('pull_request_edit_session_id')->nullable()->after('user_branch_id');
            $table->foreign('pull_request_edit_session_id')->references('id')->on('pull_request_edit_sessions')->onDelete('set null');
            $table->index(['pull_request_edit_session_id']);
        });

        Schema::table('document_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('pull_request_edit_session_id')->nullable()->after('user_branch_id');
            $table->foreign('pull_request_edit_session_id')->references('id')->on('pull_request_edit_sessions')->onDelete('set null');
            $table->index(['pull_request_edit_session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropForeign(['pull_request_edit_session_id']);
            $table->dropIndex(['pull_request_edit_session_id']);
            $table->dropColumn('pull_request_edit_session_id');
        });

        Schema::table('document_categories', function (Blueprint $table) {
            $table->dropForeign(['pull_request_edit_session_id']);
            $table->dropIndex(['pull_request_edit_session_id']);
            $table->dropColumn('pull_request_edit_session_id');
        });
    }
}; 