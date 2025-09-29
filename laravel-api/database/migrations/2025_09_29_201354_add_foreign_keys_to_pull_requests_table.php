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
            $table->foreign(['organization_id'])->references(['id'])->on('organizations')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_branch_id'])->references(['id'])->on('user_branches')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropForeign('pull_requests_organization_id_foreign');
            $table->dropForeign('pull_requests_user_branch_id_foreign');
        });
    }
};
