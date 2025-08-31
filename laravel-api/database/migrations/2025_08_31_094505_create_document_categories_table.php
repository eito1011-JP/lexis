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
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('sidebar_label');
            $table->integer('position');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('parent_id')->nullable();
            $table->foreignId('user_branch_id')->nullable();
            $table->foreignId('pull_request_edit_session_id')->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('parent_id')->references('id')->on('document_categories')->onDelete('cascade');
            $table->foreign('user_branch_id')->references('id')->on('user_branches')->onDelete('set null');
            $table->foreign('pull_request_edit_session_id')->references('id')->on('pull_request_edit_sessions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_categories');
    }
};
