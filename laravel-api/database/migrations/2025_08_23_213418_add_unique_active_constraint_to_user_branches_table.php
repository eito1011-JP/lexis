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
        // user_idとis_activeがtrueの組み合わせで一意制約を追加
        // is_activeがfalseの場合は複数存在可能（partial unique index）
        DB::statement('CREATE UNIQUE INDEX unique_user_active_branch ON user_branches (user_id) WHERE is_active = 1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 一意制約を削除
        DB::statement('DROP INDEX IF EXISTS unique_user_active_branch');
    }
};
