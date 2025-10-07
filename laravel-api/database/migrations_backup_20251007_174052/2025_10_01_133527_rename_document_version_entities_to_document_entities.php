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
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropForeign('fk_dv_entity');
        });

        // テーブル名を変更
        Schema::rename('document_version_entities', 'document_entities');

        // 外部キー制約を再作成
        Schema::table('document_versions', function (Blueprint $table) {
            $table->foreign(['entity_id'], 'fk_dv_entity')->references(['id'])->on('document_entities')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 外部キー制約を一時的に削除
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropForeign('fk_dv_entity');
        });

        // テーブル名を元に戻す
        Schema::rename('document_entities', 'document_version_entities');

        // 外部キー制約を再作成
        Schema::table('document_versions', function (Blueprint $table) {
            $table->foreign(['entity_id'], 'fk_dv_entity')->references(['id'])->on('document_version_entities')->onUpdate('no action')->onDelete('restrict');
        });
    }
};
