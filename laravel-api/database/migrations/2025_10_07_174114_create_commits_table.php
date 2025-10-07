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
        Schema::create('commits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('parent_commit_id')->nullable();
            $table->unsignedBigInteger('user_branch_id')->index('commits_user_branch_id_foreign');
            $table->unsignedBigInteger('user_id')->nullable()->index('commits_user_id_foreign');
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commits');
    }
};
