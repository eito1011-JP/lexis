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
        Schema::create('activity_log_on_pull_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index('fk_activity_log_pr_user_id');
            $table->unsignedBigInteger('pull_request_id')->index('fk_activity_log_pr_request_id');
            $table->unsignedBigInteger('comment_id')->nullable()->index('fk_activity_log_pr_comment_id');
            $table->unsignedBigInteger('reviewer_id')->nullable()->index('fk_activity_log_pr_reviewer_id');
            $table->unsignedBigInteger('pull_request_edit_session_id')->nullable()->index('fk_activity_log_pr_edit_session_id');
            $table->string('action');
            $table->string('fix_request_token')->nullable();
            $table->string('old_pull_request_title')->nullable();
            $table->string('new_pull_request_title')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log_on_pull_requests');
    }
};
