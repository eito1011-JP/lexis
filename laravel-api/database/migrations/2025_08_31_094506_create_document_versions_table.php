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
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('user_branch_id')->nullable();
            $table->foreignId('pull_request_edit_session_id')->nullable();
            $table->string('file_path');
            $table->string('status')->default('draft');
            $table->text('content');
            $table->string('original_blob_sha')->nullable();
            $table->string('slug');
            $table->foreignId('category_id');
            $table->string('sidebar_label');
            $table->integer('file_order');
            $table->string('last_edited_by')->nullable();
            $table->string('last_reviewed_by')->nullable();
            $table->boolean('is_public')->default(1);
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_branch_id')->references('id')->on('user_branches')->onDelete('set null');
            $table->foreign('pull_request_edit_session_id')->references('id')->on('pull_request_edit_sessions')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('document_categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
