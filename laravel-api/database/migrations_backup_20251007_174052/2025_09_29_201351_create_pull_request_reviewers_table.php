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
        Schema::create('pull_request_reviewers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pull_request_id')->index('pull_request_reviewers_pull_request_id_foreign');
            $table->unsignedBigInteger('user_id')->index('pull_request_reviewers_user_id_foreign');
            $table->string('action_status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_request_reviewers');
    }
};
