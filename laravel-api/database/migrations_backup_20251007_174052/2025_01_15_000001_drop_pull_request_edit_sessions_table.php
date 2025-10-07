<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 外部キー制約を安全に削除
        $this->dropForeignKeyIfExists('activity_log_on_pull_requests', 'fk_activity_log_pr_edit_session_id');
        $this->dropColumnIfExists('activity_log_on_pull_requests', 'pull_request_edit_session_id');
        
        $this->dropForeignKeyIfExists('category_versions', 'document_categories_pull_request_edit_session_id_foreign');
        $this->dropColumnIfExists('category_versions', 'pull_request_edit_session_id');
        
        $this->dropForeignKeyIfExists('document_versions', 'document_versions_pull_request_edit_session_id_foreign');
        $this->dropColumnIfExists('document_versions', 'pull_request_edit_session_id');
        
        $this->dropForeignKeyIfExists('pull_request_edit_session_diffs', 'fk_pr_edit_session_diffs_session_id');
        
        Schema::dropIfExists('pull_request_edit_session_diffs');
        Schema::dropIfExists('pull_request_edit_sessions');
    }
    
    /**
     * 外部キーが存在する場合のみ削除
     */
    private function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        
        $schemaName = DB::getDatabaseName();
        $foreignKeyExists = DB::select(
            "SELECT CONSTRAINT_NAME 
             FROM information_schema.TABLE_CONSTRAINTS 
             WHERE TABLE_SCHEMA = ? 
             AND TABLE_NAME = ? 
             AND CONSTRAINT_NAME = ? 
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$schemaName, $table, $foreignKey]
        );
        
        if (!empty($foreignKeyExists)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($foreignKey) {
                $tableBlueprint->dropForeign($foreignKey);
            });
        }
    }
    
    /**
     * カラムが存在する場合のみ削除
     */
    private function dropColumnIfExists(string $table, string $column): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column) {
                $tableBlueprint->dropColumn($column);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('pull_request_edit_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pull_request_id')->constrained('pull_requests')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('token')->unique();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        
        // 外部キー制約を再作成
        Schema::table('activity_log_on_pull_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('pull_request_edit_session_id')->nullable();
            $table->foreign('pull_request_edit_session_id', 'fk_activity_log_pr_edit_session_id')->references('id')->on('pull_request_edit_sessions')->onDelete('cascade');
        });
        
        Schema::table('category_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('pull_request_edit_session_id')->nullable();
            $table->foreign('pull_request_edit_session_id', 'document_categories_pull_request_edit_session_id_foreign')->references('id')->on('pull_request_edit_sessions')->onDelete('set null');
        });
        
        Schema::table('document_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('pull_request_edit_session_id')->nullable();
            $table->foreign('pull_request_edit_session_id', 'document_versions_pull_request_edit_session_id_foreign')->references('id')->on('pull_request_edit_sessions')->onDelete('set null');
        });
        
        Schema::table('pull_request_edit_session_diffs', function (Blueprint $table) {
            $table->unsignedBigInteger('pull_request_edit_session_id');
            $table->foreign('pull_request_edit_session_id', 'fk_pr_edit_session_diffs_session_id')->references('id')->on('pull_request_edit_sessions')->onDelete('cascade');
        });
    }
};
