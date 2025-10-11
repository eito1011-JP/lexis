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
        Schema::create('activity_log_on_mains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_branch_id')->nullable()->comment('マージ元のブランチ（構造変更の場合はNULL）');
            $table->unsignedBigInteger('pull_request_id')->nullable()->comment('マージされたPR（構造変更の場合はNULL）');
            $table->enum('action', ['merged', 'structure_changed'])->comment('アクションタイプ');
            $table->string('message', 50)->nullable()->comment('表示用メッセージ');
            $table->unsignedBigInteger('actor_id')->nullable()->comment('実行者');
            $table->timestamps();
            
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('user_branch_id')->references('id')->on('user_branches')->onDelete('set null');
            $table->foreign('pull_request_id')->references('id')->on('pull_requests')->onDelete('set null');
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log_on_mains');
    }
};
