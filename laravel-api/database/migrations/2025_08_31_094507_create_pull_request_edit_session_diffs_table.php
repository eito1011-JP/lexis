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
        Schema::create('pull_request_edit_session_diffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_edit_session_id');
            $table->string('target_type');
            $table->string('diff_type')->nullable();
            $table->foreignId('original_version_id')->nullable();
            $table->foreignId('current_version_id')->nullable();
            $table->timestamps();
            $table->foreign('pull_request_edit_session_id', 'fk_pr_edit_session_diffs_session_id')
                  ->references('id')->on('pull_request_edit_sessions')->onDelete('cascade');
            $table->foreign('original_version_id', 'fk_pr_edit_session_diffs_original_ver')
                  ->references('id')->on('document_versions')->onDelete('set null');
            $table->foreign('current_version_id', 'fk_pr_edit_session_diffs_current_ver')
                  ->references('id')->on('document_versions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_request_edit_session_diffs');
    }
};
