<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // デフォルト組織のIDを取得
        $defaultOrganization = DB::table('organizations')->where('slug', 'default')->first();
        if (!$defaultOrganization) {
            $organizationId = DB::table('organizations')->insertGetId([
                'slug' => 'default',
                'name' => 'Default Organization',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $organizationId = $defaultOrganization->id;
        }

        // document_categories テーブルに organization_id を追加
        Schema::table('document_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->index(['organization_id']);
        });

        // document_versions テーブルに organization_id を追加
        Schema::table('document_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->index(['organization_id']);
        });

        // pull_requests テーブルに organization_id を追加
        Schema::table('pull_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->index(['organization_id']);
        });

        // user_branches テーブルに organization_id を追加
        Schema::table('user_branches', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->index(['organization_id']);
        });

        // pull_request_edit_sessions テーブルに organization_id を追加
        Schema::table('pull_request_edit_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->index(['organization_id']);
        });

        // pull_request_edit_session_diffs テーブルに organization_id を追加
        Schema::table('pull_request_edit_session_diffs', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->index(['organization_id']);
        });

        // pull_request_reviewers テーブルに organization_id を追加
        Schema::table('pull_request_reviewers', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->index(['organization_id']);
        });

        // comments テーブルに organization_id を追加
        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->index(['organization_id']);
        });

        // edit_start_versions テーブルに organization_id を追加
        Schema::table('edit_start_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->index(['organization_id']);
        });

        // fix_requests テーブルに organization_id を追加
        Schema::table('fix_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->index(['organization_id']);
        });

        // activity_log_on_pull_requests テーブルに organization_id を追加
        Schema::table('activity_log_on_pull_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->index(['organization_id']);
        });

        // 既存のレコードにデフォルト組織を設定
        DB::table('document_categories')->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        DB::table('document_versions')->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        DB::table('pull_requests')->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        DB::table('user_branches')->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        DB::table('pull_request_edit_sessions')->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        DB::table('pull_request_edit_session_diffs')->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        DB::table('pull_request_reviewers')->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        DB::table('comments')->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        DB::table('edit_start_versions')->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        DB::table('fix_requests')->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        DB::table('activity_log_on_pull_requests')->whereNull('organization_id')->update(['organization_id' => $organizationId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_log_on_pull_requests', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('fix_requests', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('edit_start_versions', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('pull_request_reviewers', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('pull_request_edit_session_diffs', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('pull_request_edit_sessions', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('user_branches', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('pull_requests', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('document_categories', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
