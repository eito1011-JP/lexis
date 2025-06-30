<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentCategoryPrStatus;
use App\Http\Requests\FetchDiffRequest;
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

            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // ユーザーブランチと関連データを一括取得
            $userBranch = $user->userBranches()->active()->where('pr_status', DocumentCategoryPrStatus::NONE->value)
                ->with([
                    'editStartVersions',
                    'editStartVersions.originalDocumentVersion',
                    'editStartVersions.currentDocumentVersion',
                    'editStartVersions.originalDocumentVersion.category',
                    'editStartVersions.currentDocumentVersion.category',
                    'editStartVersions.originalCategory',
                    'editStartVersions.currentCategory',
                ])
                ->findOrFail($request->user_branch_id);

            if (! $userBranch) {
                return response()->json([
                    'error' => 'ユーザーブランチが見つかりません',
                ], 404);
            }

            // document_versionsを取得（current_version_idとoriginal_version_idに紐づいたもの）
            $documentVersions = collect();
            $documentCategories = collect();
            $originalDocumentVersions = collect();
            $originalDocumentCategories = collect();

            foreach ($userBranch->editStartVersions as $editStartVersion) {
                if ($editStartVersion->target_type === 'document') {
                    if ($editStartVersion->currentDocumentVersion) {
                        $documentVersions->push($editStartVersion->currentDocumentVersion);
                    }
                    // original_version_idとcurrent_version_idが異なる場合のみoriginalに追加（新規作成でない場合）
                    if ($editStartVersion->originalDocumentVersion &&
                        $editStartVersion->original_version_id !== $editStartVersion->current_version_id) {
                        $originalDocumentVersions->push($editStartVersion->originalDocumentVersion);
                    }
                } elseif ($editStartVersion->target_type === 'category') {
                    if ($editStartVersion->currentCategory) {
                        $documentCategories->push($editStartVersion->currentCategory);
                    }
                    // original_version_idとcurrent_version_idが異なる場合のみoriginalに追加（新規作成でない場合）
                    if ($editStartVersion->originalCategory &&
                        $editStartVersion->original_version_id !== $editStartVersion->current_version_id) {
                        $originalDocumentCategories->push($editStartVersion->originalCategory);
                    }
                }
            }

            return response()->json([
                'document_versions' => $documentVersions->unique('id')->values(),
                'document_categories' => $documentCategories->unique('id')->values(),
                'original_document_versions' => $originalDocumentVersions->unique('id')->values(),
                'original_document_categories' => $originalDocumentCategories->unique('id')->values(),
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
