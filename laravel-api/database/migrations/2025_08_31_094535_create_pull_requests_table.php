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
        Schema::create('pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_branch_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('github_url')->nullable();
            $table->integer('pr_number')->nullable();
            $table->string('status')->default('opened');
            $table->timestamps();
            $table->foreign('user_branch_id')->references('id')->on('user_branches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_requests');
    }
};
