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
        Schema::table('fix_requests', function (Blueprint $table) {
            $table->foreign(['base_category_version_id'])->references(['id'])->on('category_versions')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['base_document_version_id'])->references(['id'])->on('document_versions')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['document_category_id'])->references(['id'])->on('category_versions')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['document_version_id'])->references(['id'])->on('document_versions')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['pull_request_id'])->references(['id'])->on('pull_requests')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fix_requests', function (Blueprint $table) {
            $table->dropForeign('fix_requests_base_category_version_id_foreign');
            $table->dropForeign('fix_requests_base_document_version_id_foreign');
            $table->dropForeign('fix_requests_document_category_id_foreign');
            $table->dropForeign('fix_requests_document_version_id_foreign');
            $table->dropForeign('fix_requests_pull_request_id_foreign');
            $table->dropForeign('fix_requests_user_id_foreign');
        });
    }
};
