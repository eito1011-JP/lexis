<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 既存のデータをバックアップ
        DB::statement('
            CREATE TABLE pull_request_edit_session_diffs_backup AS 
            SELECT * FROM pull_request_edit_session_diffs
        ');

        // 古いテーブルを削除
        DB::statement('DROP TABLE pull_request_edit_session_diffs');

        // 新しいチェック制約でテーブルを再作成
        DB::statement("
            CREATE TABLE pull_request_edit_session_diffs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pull_request_edit_session_id INTEGER NOT NULL,
                target_type TEXT NOT NULL CHECK (target_type IN ('document', 'category')),
                diff_type TEXT CHECK (diff_type IN ('created', 'updated', 'deleted')),
                original_version_id INTEGER,
                current_version_id INTEGER,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (pull_request_edit_session_id) REFERENCES pull_request_edit_sessions(id) ON DELETE CASCADE
            )
        ");

        // バックアップからデータを復元（target_typeを変換）
        DB::statement("
            INSERT INTO pull_request_edit_session_diffs 
            SELECT 
                id,
                pull_request_edit_session_id,
                CASE 
                    WHEN target_type = 'documents' THEN 'document'
                    WHEN target_type = 'categories' THEN 'category'
                    ELSE target_type
                END as target_type,
                diff_type,
                original_version_id,
                current_version_id,
                created_at,
                updated_at
            FROM pull_request_edit_session_diffs_backup
        ");

        // バックアップテーブルを削除
        DB::statement('DROP TABLE pull_request_edit_session_diffs_backup');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 既存のデータをバックアップ
        DB::statement('
            CREATE TABLE pull_request_edit_session_diffs_backup AS 
            SELECT * FROM pull_request_edit_session_diffs
        ');

        // 古いテーブルを削除
        DB::statement('DROP TABLE pull_request_edit_session_diffs');

        // 元のチェック制約でテーブルを再作成
        DB::statement("
            CREATE TABLE pull_request_edit_session_diffs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pull_request_edit_session_id INTEGER NOT NULL,
                target_type TEXT NOT NULL CHECK (target_type IN ('documents', 'categories')),
                diff_type TEXT CHECK (diff_type IN ('created', 'updated', 'deleted')),
                original_version_id INTEGER,
                current_version_id INTEGER,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (pull_request_edit_session_id) REFERENCES pull_request_edit_sessions(id) ON DELETE CASCADE
            )
        ");

        // バックアップからデータを復元（target_typeを元に戻す）
        DB::statement("
            INSERT INTO pull_request_edit_session_diffs 
            SELECT 
                id,
                pull_request_edit_session_id,
                CASE 
                    WHEN target_type = 'document' THEN 'documents'
                    WHEN target_type = 'category' THEN 'categories'
                    ELSE target_type
                END as target_type,
                diff_type,
                original_version_id,
                current_version_id,
                created_at,
                updated_at
            FROM pull_request_edit_session_diffs_backup
        ");

        // バックアップテーブルを削除
        DB::statement('DROP TABLE pull_request_edit_session_diffs_backup');
    }
};
