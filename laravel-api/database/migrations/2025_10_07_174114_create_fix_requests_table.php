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
            $table->bigIncrements('id');
            $table->string('token')->nullable();
            $table->unsignedBigInteger('document_version_id')->nullable()->index('fix_requests_document_version_id_foreign');
            $table->unsignedBigInteger('document_category_id')->nullable()->index('fix_requests_document_category_id_foreign');
            $table->unsignedBigInteger('base_document_version_id')->nullable()->index('fix_requests_base_document_version_id_foreign');
            $table->unsignedBigInteger('base_category_version_id')->nullable()->index('fix_requests_base_category_version_id_foreign');
            $table->unsignedBigInteger('user_id')->nullable()->index('fix_requests_user_id_foreign');
            $table->unsignedBigInteger('pull_request_id')->index('fix_requests_pull_request_id_foreign');
            $table->string('status')->default('pending');
            $table->timestamps();
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
