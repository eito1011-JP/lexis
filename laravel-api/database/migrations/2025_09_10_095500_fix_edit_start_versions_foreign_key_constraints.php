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
        Schema::table('edit_start_versions', function (Blueprint $table) {
            // 既存の外部キー制約を削除
            $table->dropForeign(['original_version_id']);
            $table->dropForeign(['current_version_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edit_start_versions', function (Blueprint $table) {
            // 外部キー制約を復元
            $table->foreign('original_version_id')->references('id')->on('document_versions')->onDelete('set null');
            $table->foreign('current_version_id')->references('id')->on('document_versions')->onDelete('cascade');
        });
    }
};
