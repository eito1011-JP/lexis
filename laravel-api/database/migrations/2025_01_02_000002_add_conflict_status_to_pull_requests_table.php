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
        Schema::table('pull_requests', function (Blueprint $table) {
            // SQLiteではenumの変更が制限されているため、一時的にテーブルを再作成
            $table->dropColumn('status');
        });

        Schema::table('pull_requests', function (Blueprint $table) {
            $table->enum('status', ['opened', 'merged', 'closed', 'conflict'])->default('opened');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('pull_requests', function (Blueprint $table) {
            $table->enum('status', ['opened', 'merged', 'closed'])->default('opened');
        });
    }
}; 