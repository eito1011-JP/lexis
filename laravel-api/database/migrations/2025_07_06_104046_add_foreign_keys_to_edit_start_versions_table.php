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
        // 注意: 同じカラムが複数のテーブルを参照する場合、
        // 外部キー制約を設定するのは複雑です。
        // アプリケーション側で適切に制御することを推奨します。

        // 必要に応じて、インデックスを追加してパフォーマンスを向上させることができます
        Schema::table('edit_start_versions', function (Blueprint $table) {
            $table->index(['original_version_id', 'target_type']);
            $table->index(['current_version_id', 'target_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edit_start_versions', function (Blueprint $table) {
            $table->dropIndex(['original_version_id', 'target_type']);
            $table->dropIndex(['current_version_id', 'target_type']);
        });
    }
};
