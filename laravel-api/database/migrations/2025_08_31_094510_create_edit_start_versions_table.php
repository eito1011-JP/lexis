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
        Schema::create('edit_start_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_branch_id')->nullable();
            $table->string('target_type');
            $table->foreignId('original_version_id')->nullable();
            $table->foreignId('current_version_id');
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('user_branch_id')->references('id')->on('user_branches')->onDelete('set null');
            $table->foreign('original_version_id')->references('id')->on('document_versions')->onDelete('set null');
            $table->foreign('current_version_id')->references('id')->on('document_versions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edit_start_versions');
    }
};
