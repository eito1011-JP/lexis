<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentCategoryPrStatus;
use App\Http\Requests\FetchDiffRequest;
use App\Models\UserBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserBranchController extends ApiBaseController
{
    /**
     * Git差分チェック
     */
    public function hasUserChanges(Request $request): JsonResponse
    {
        try {
            // Cookieセッションからユーザー情報を取得
            $loginUser = $this->getUserFromSession();

            if (! $loginUser) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // アクティブなユーザーブランチを取得
            $activeBranch = $loginUser->userBranches()->active()->where('pr_status', DocumentCategoryPrStatus::NONE->value)->first();

            $hasUserChanges = ! is_null($activeBranch);
            $userBranchId = $hasUserChanges ? $activeBranch->id : null;

            return response()->json([
                'has_user_changes' => $hasUserChanges,
                'user_branch_id' => $userBranchId,
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking document versions: '.$e->getMessage());

            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * プルリクエスト作成
     */
    public function createPr(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string',
                'body' => 'nullable|string',
                'branch' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first(),
                ], 400);
            }

            // プルリクエスト作成の実装
            // 実際の実装ではGitHub APIなどを使用

            return response()->json([
                'success' => true,
                'message' => 'プルリクエストを作成しました',
                'prUrl' => 'https://github.com/example/pull/123',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'プルリクエストの作成に失敗しました',
            ], 500);
        }
    }

    /**
     * Git差分取得
     */
    public function fetchDiff(FetchDiffRequest $request): JsonResponse
    {
        try {
            // ユーザーブランチと関連データを一括取得
            $userBranch = UserBranch::where('pr_status', DocumentCategoryPrStatus::NONE->value)
                ->with([
                    'editStartVersions',
                    'editStartVersions.originalDocumentVersion',
                    'editStartVersions.currentDocumentVersion',
                    'editStartVersions.originalDocumentVersion.category',
                    'editStartVersions.currentDocumentVersion.category',
                    'documentVersions',
                    'documentVersions.category',
                    'documentCategories',
                ])
                ->findOrFail($request->user_branch_id);

            if (! $userBranch) {
                return response()->json([
                    'error' => 'ユーザーブランチが見つかりません',
                ], 404);
            }

            return response()->json([
                'user_branch' => $userBranch,
                'edit_start_versions' => $userBranch->editStartVersions,
                'document_versions' => $userBranch->documentVersions,
                'document_categories' => $userBranch->documentCategories,
            ]);

        } catch (\Exception $e) {
            Log::error('Git差分の取得に失敗しました: '.$e->getMessage());

            return response()->json([
                'error' => 'Git差分の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * ブランチスナップショット初期化
     */
    public static function initBranchSnapshot(int $userId, string $email): void
    {
        try {
            // 最新のコミットハッシュを取得（GitHub APIを使用）
            $latestCommit = self::findLatestCommit();

            $timestamp = date('Ymd');
            $branchName = "feature/{$email}_{$timestamp}";

            // user_branchesテーブルに新しいブランチを挿入
            DB::table('user_branches')->insert([
                'user_id' => $userId,
                'branch_name' => $branchName,
                'snapshot_commit' => $latestCommit,
                'is_active' => 1,
                'pr_status' => 'none',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('ブランチスナップショット初期化エラー: '.$e->getMessage());

            throw new \Exception('ブランチの作成に失敗しました');
        }
    }

    /**
     * 最新のコミットハッシュを取得
     */
    private static function findLatestCommit(): string
    {
        try {
            // GitHub APIを使用して最新のコミットハッシュを取得
            // 実際の実装ではGitHub APIクライアントを使用
            $githubToken = config('services.github.token');
            $githubOwner = config('services.github.owner');
            $githubRepo = config('services.github.repo');

            // 簡易的な実装（実際にはGitHub APIを使用）
            return 'latest_commit_hash';

        } catch (\Exception $e) {
            Log::error('GitHub APIエラー: '.$e->getMessage());

            throw new \Exception('最新のコミット取得に失敗しました');
        }
    }
}
