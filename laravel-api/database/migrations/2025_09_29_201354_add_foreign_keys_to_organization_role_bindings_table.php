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
        Schema::table('organization_role_bindings', function (Blueprint $table) {
            $table->foreign(['organization_id'])->references(['id'])->on('organizations')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_role_bindings', function (Blueprint $table) {
            $table->dropForeign('organization_role_bindings_organization_id_foreign');
            $table->dropForeign('organization_role_bindings_user_id_foreign');
        });
    }
};
