<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLiteの場合、enum/check制約の変更は複雑なため、テーブルを再作成する

        // document_versionsテーブルの更新
        DB::statement('PRAGMA foreign_keys = OFF');

        // 既存のデータをバックアップ
        $documentVersions = DB::table('document_versions')->get();

        // 一時テーブルを作成
        Schema::create('document_versions_temp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('user_branch_id');
            $table->string('file_path');
            $table->enum('status', ['draft', 'pushed', 'merged', 'fix-request'])->default('draft');
            $table->text('content')->nullable();
            $table->string('original_blob_sha')->nullable();
            $table->string('slug')->nullable();
            $table->integer('category_id')->nullable();
            $table->string('sidebar_label')->nullable();
            $table->integer('file_order')->default(1);
            $table->string('last_edited_by')->nullable();
            $table->string('last_reviewed_by')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_public')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        // データを新しいテーブルに移行
        foreach ($documentVersions as $version) {
            DB::table('document_versions_temp')->insert([
                'id' => $version->id,
                'user_id' => $version->user_id,
                'user_branch_id' => $version->user_branch_id,
                'file_path' => $version->file_path,
                'status' => $version->status,
                'content' => $version->content,
                'original_blob_sha' => $version->original_blob_sha,
                'slug' => $version->slug,
                'category_id' => $version->category_id,
                'sidebar_label' => $version->sidebar_label,
                'file_order' => $version->file_order,
                'last_edited_by' => $version->last_edited_by,
                'last_reviewed_by' => $version->last_reviewed_by,
                'is_deleted' => $version->is_deleted,
                'is_public' => $version->is_public,
                'deleted_at' => $version->deleted_at,
                'created_at' => $version->created_at,
                'updated_at' => $version->updated_at,
            ]);
        }

        // 元のテーブルを削除
        Schema::dropIfExists('document_versions');

        // 一時テーブルを元の名前にリネーム
        Schema::rename('document_versions_temp', 'document_versions');

        // document_categoriesテーブルの更新
        $documentCategories = DB::table('document_categories')->get();

        Schema::create('document_categories_temp', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('sidebar_label');
            $table->integer('position')->default(1);
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'pushed', 'merged', 'fix-request'])->default('draft');
            $table->integer('parent_id')->nullable();
            $table->integer('user_branch_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        // データを新しいテーブルに移行
        foreach ($documentCategories as $category) {
            DB::table('document_categories_temp')->insert([
                'id' => $category->id,
                'slug' => $category->slug,
                'sidebar_label' => $category->sidebar_label,
                'position' => $category->position,
                'description' => $category->description,
                'status' => $category->status,
                'parent_id' => $category->parent_id,
                'user_branch_id' => $category->user_branch_id,
                'is_deleted' => $category->is_deleted,
                'deleted_at' => $category->deleted_at,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ]);
        }

        // 元のテーブルを削除
        Schema::dropIfExists('document_categories');

        // 一時テーブルを元の名前にリネーム
        Schema::rename('document_categories_temp', 'document_categories');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 元に戻す場合はfix-requestを除く制約に戻す
        DB::statement('PRAGMA foreign_keys = OFF');

        // document_versionsテーブルの復元
        $documentVersions = DB::table('document_versions')->get();

        Schema::create('document_versions_temp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('user_branch_id');
            $table->string('file_path');
            $table->enum('status', ['draft', 'pushed', 'merged'])->default('draft');
            $table->text('content')->nullable();
            $table->string('original_blob_sha')->nullable();
            $table->string('slug')->nullable();
            $table->integer('category_id')->nullable();
            $table->string('sidebar_label')->nullable();
            $table->integer('file_order')->default(1);
            $table->string('last_edited_by')->nullable();
            $table->string('last_reviewed_by')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_public')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        // fix-requestステータスのレコードは削除または別のステータスに変更
        foreach ($documentVersions as $version) {
            $status = $version->status === 'fix-request' ? 'draft' : $version->status;

            DB::table('document_versions_temp')->insert([
                'id' => $version->id,
                'user_id' => $version->user_id,
                'user_branch_id' => $version->user_branch_id,
                'file_path' => $version->file_path,
                'status' => $status,
                'content' => $version->content,
                'original_blob_sha' => $version->original_blob_sha,
                'slug' => $version->slug,
                'category_id' => $version->category_id,
                'sidebar_label' => $version->sidebar_label,
                'file_order' => $version->file_order,
                'last_edited_by' => $version->last_edited_by,
                'last_reviewed_by' => $version->last_reviewed_by,
                'is_deleted' => $version->is_deleted,
                'is_public' => $version->is_public,
                'deleted_at' => $version->deleted_at,
                'created_at' => $version->created_at,
                'updated_at' => $version->updated_at,
            ]);
        }

        Schema::dropIfExists('document_versions');
        Schema::rename('document_versions_temp', 'document_versions');

        // document_categoriesテーブルの復元
        $documentCategories = DB::table('document_categories')->get();

        Schema::create('document_categories_temp', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('sidebar_label');
            $table->integer('position')->default(1);
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'pushed', 'merged'])->default('draft');
            $table->integer('parent_id')->nullable();
            $table->integer('user_branch_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        foreach ($documentCategories as $category) {
            $status = $category->status === 'fix-request' ? 'draft' : $category->status;

            DB::table('document_categories_temp')->insert([
                'id' => $category->id,
                'slug' => $category->slug,
                'sidebar_label' => $category->sidebar_label,
                'position' => $category->position,
                'description' => $category->description,
                'status' => $status,
                'parent_id' => $category->parent_id,
                'user_branch_id' => $category->user_branch_id,
                'is_deleted' => $category->is_deleted,
                'deleted_at' => $category->deleted_at,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ]);
        }

        Schema::dropIfExists('document_categories');
        Schema::rename('document_categories_temp', 'document_categories');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
