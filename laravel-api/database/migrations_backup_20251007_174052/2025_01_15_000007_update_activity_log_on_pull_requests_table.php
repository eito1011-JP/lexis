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
        Schema::table('activity_log_on_pull_requests', function (Blueprint $table) {
            // 外部キー制約が存在する場合のみ削除
            $this->dropForeignKeyIfExists('activity_log_on_pull_requests', 'activity_log_on_pull_requests_pull_request_edit_session_id_foreign');
            $this->dropColumnIfExists('activity_log_on_pull_requests', 'pull_request_edit_session_id');
            $table->unsignedBigInteger('commit_id')->nullable()->after('reviewer_id');
            $table->foreign('commit_id')->references('id')->on('commits')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log_on_pull_requests', function (Blueprint $table) {
            $table->dropForeign(['commit_id']);
            $table->dropColumn('commit_id');
            $table->unsignedBigInteger('pull_request_edit_session_id')->nullable();
            $table->foreign('pull_request_edit_session_id')->references('id')->on('pull_request_edit_sessions')->onDelete('cascade');
        });
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
};
