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
        Schema::create('fix_requests', function (Blueprint $table) {
            $table->id();
            $table->string('token')->nullable();
            $table->foreignId('document_version_id')->nullable();
            $table->foreignId('document_category_id')->nullable();
            $table->foreignId('base_document_version_id')->nullable();
            $table->foreignId('base_category_version_id')->nullable();
            $table->foreignId('user_id');
            $table->foreignId('pull_request_id');
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->foreign('document_version_id')->references('id')->on('document_versions')->onDelete('set null');
            $table->foreign('document_category_id')->references('id')->on('document_categories')->onDelete('set null');
            $table->foreign('base_document_version_id')->references('id')->on('document_versions')->onDelete('set null');
            $table->foreign('base_category_version_id')->references('id')->on('document_categories')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('pull_request_id')->references('id')->on('pull_requests')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fix_requests');
    }
};
