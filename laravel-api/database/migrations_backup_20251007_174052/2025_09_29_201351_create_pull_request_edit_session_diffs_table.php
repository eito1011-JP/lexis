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
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pull_request_edit_session_id')->index('fk_pr_edit_session_diffs_session_id');
            $table->string('target_type');
            $table->string('diff_type')->nullable();
            $table->unsignedBigInteger('original_version_id')->nullable()->index('fk_pr_edit_session_diffs_original_ver');
            $table->unsignedBigInteger('current_version_id')->nullable()->index('fk_pr_edit_session_diffs_current_ver');
            $table->timestamps();
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
