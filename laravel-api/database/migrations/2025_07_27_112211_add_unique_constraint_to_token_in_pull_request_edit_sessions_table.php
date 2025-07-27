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
        Schema::table('pull_request_edit_sessions', function (Blueprint $table) {
            $table->unique('token', 'pull_request_edit_sessions_token_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pull_request_edit_sessions', function (Blueprint $table) {
            $table->dropUnique('pull_request_edit_sessions_token_unique');
        });
    }
};
