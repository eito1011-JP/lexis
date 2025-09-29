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
        Schema::create('edit_start_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_branch_id')->nullable()->index('edit_start_versions_user_branch_id_foreign');
            $table->string('target_type');
            $table->unsignedBigInteger('original_version_id')->nullable()->index('edit_start_versions_original_version_id_foreign');
            $table->unsignedBigInteger('current_version_id')->index('edit_start_versions_current_version_id_foreign');
            $table->boolean('is_deleted')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edit_start_versions');
    }
};
