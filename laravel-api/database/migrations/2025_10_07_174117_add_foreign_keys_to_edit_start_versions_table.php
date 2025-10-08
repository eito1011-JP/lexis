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
            $table->foreign(['commit_id'])->references(['id'])->on('commits')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['user_branch_id'])->references(['id'])->on('user_branches')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edit_start_versions', function (Blueprint $table) {
            $table->dropForeign('edit_start_versions_commit_id_foreign');
            $table->dropForeign('edit_start_versions_user_branch_id_foreign');
        });
    }
};
