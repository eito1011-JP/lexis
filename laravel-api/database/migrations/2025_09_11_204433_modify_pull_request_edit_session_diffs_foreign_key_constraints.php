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
        Schema::table('pull_request_edit_session_diffs', function (Blueprint $table) {
            // 既存の外部キー制約を削除
            $table->dropForeign('fk_pr_edit_session_diffs_original_ver');
            $table->dropForeign('fk_pr_edit_session_diffs_current_ver');
            
            // current_version_idとoriginal_version_idは、target_typeに応じて
            // document_versionsまたはdocument_categoriesを参照するため、
            // 外部キー制約を削除してアプリケーション側で整合性を管理する
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pull_request_edit_session_diffs', function (Blueprint $table) {
            // 外部キー制約を復元
            $table->foreign('original_version_id', 'fk_pr_edit_session_diffs_original_ver')
                  ->references('id')->on('document_versions')->onDelete('set null');
            $table->foreign('current_version_id', 'fk_pr_edit_session_diffs_current_ver')
                  ->references('id')->on('document_versions')->onDelete('set null');
        });
    }
};
