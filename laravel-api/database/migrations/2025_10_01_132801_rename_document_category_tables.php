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
        // 外部キー制約を一時的に削除
        Schema::table('document_categories', function (Blueprint $table) {
            $table->dropForeign('fk_dc_entity');
            $table->dropForeign('fk_dc_parent_entity');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropForeign('fk_dv_category_entity');
        });

        Schema::table('fix_requests', function (Blueprint $table) {
            $table->dropForeign('fix_requests_document_category_id_foreign');
            $table->dropForeign('fix_requests_base_category_version_id_foreign');
        });

        // テーブル名を変更
        Schema::rename('document_category_entities', 'category_entities');
        Schema::rename('document_categories', 'category_versions');

        // 外部キー制約を再作成
        Schema::table('category_versions', function (Blueprint $table) {
            $table->foreign(['entity_id'], 'fk_cv_entity')->references(['id'])->on('category_entities')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['parent_entity_id'], 'fk_cv_parent_entity')->references(['id'])->on('category_entities')->onUpdate('no action')->onDelete('restrict');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->foreign(['category_entity_id'], 'fk_dv_category_entity')->references(['id'])->on('category_entities')->onUpdate('no action')->onDelete('restrict');
        });

        Schema::table('fix_requests', function (Blueprint $table) {
            $table->foreign(['document_category_id'], 'fix_requests_document_category_id_foreign')->references(['id'])->on('category_versions')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['base_category_version_id'], 'fix_requests_base_category_version_id_foreign')->references(['id'])->on('category_versions')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 外部キー制約を一時的に削除
        Schema::table('category_versions', function (Blueprint $table) {
            $table->dropForeign('fk_cv_entity');
            $table->dropForeign('fk_cv_parent_entity');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropForeign('fk_dv_category_entity');
        });

        Schema::table('fix_requests', function (Blueprint $table) {
            $table->dropForeign('fix_requests_document_category_id_foreign');
            $table->dropForeign('fix_requests_base_category_version_id_foreign');
        });

        // テーブル名を元に戻す
        Schema::rename('category_entities', 'document_category_entities');
        Schema::rename('category_versions', 'document_categories');

        // 外部キー制約を再作成
        Schema::table('document_categories', function (Blueprint $table) {
            $table->foreign(['entity_id'], 'fk_dc_entity')->references(['id'])->on('document_category_entities')->onUpdate('no action')->onDelete('restrict');
            $table->foreign(['parent_entity_id'], 'fk_dc_parent_entity')->references(['id'])->on('document_category_entities')->onUpdate('no action')->onDelete('restrict');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->foreign(['category_entity_id'], 'fk_dv_category_entity')->references(['id'])->on('document_category_entities')->onUpdate('no action')->onDelete('restrict');
        });

        Schema::table('fix_requests', function (Blueprint $table) {
            $table->foreign(['document_category_id'], 'fix_requests_document_category_id_foreign')->references(['id'])->on('document_categories')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['base_category_version_id'], 'fix_requests_base_category_version_id_foreign')->references(['id'])->on('document_categories')->onUpdate('no action')->onDelete('set null');
        });
    }
};
