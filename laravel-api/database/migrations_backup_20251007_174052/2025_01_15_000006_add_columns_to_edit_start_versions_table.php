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
            $table->unsignedBigInteger('commit_id')->nullable()->after('user_branch_id');
            $table->unsignedBigInteger('entity_id')->after('target_type');
            $table->foreign('commit_id')->references('id')->on('commits')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edit_start_versions', function (Blueprint $table) {
            $table->dropForeign('commit_id');
            $table->dropColumn(['commit_id', 'entity_id']);
        });
    }
};
