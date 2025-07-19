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
            $table->foreignId('document_version_id')->nullable()->constrained('document_versions')->onDelete('cascade');
            $table->foreignId('document_category_id')->nullable()->constrained('document_categories')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('pull_request_id')->constrained('pull_requests')->onDelete('cascade');
            $table->timestamps();

            // document_version_idまたはdocument_category_idのどちらかが必須
            $table->index(['document_version_id', 'document_category_id']);
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
