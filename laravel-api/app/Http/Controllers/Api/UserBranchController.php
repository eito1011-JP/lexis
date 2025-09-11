<?php

namespace App\Http\Controllers\Api;

use App\Dto\UseCase\UserBranch\FetchDiffDto;
use App\Http\Requests\FetchDiffRequest;
use App\Services\DocumentDiffService;
use App\UseCases\UserBranch\FetchDiffUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserBranchController extends ApiBaseController
{
    protected DocumentDiffService $documentDiffService;
    protected FetchDiffUseCase $fetchDiffUseCase;

    public function __construct(
        DocumentDiffService $documentDiffService,
        FetchDiffUseCase $fetchDiffUseCase
    ) {
        $this->documentDiffService = $documentDiffService;
        $this->fetchDiffUseCase = $fetchDiffUseCase;
    }

    /**
     * Git差分チェック
     */
    public function hasUserChanges(Request $request): JsonResponse
    {
        try {
            // Cookieセッションからユーザー情報を取得
            $loginUser = $this->user();

            if (! $loginUser) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // アクティブなユーザーブランチを取得
            $activeBranch = $loginUser->userBranches()->active()->first();

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
     * Git差分取得
     */
    public function fetchDiff(): JsonResponse
    {
        try {
            $user = $this->user();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // UseCaseを実行
            $diffResult = $this->fetchDiffUseCase->execute($user);

            return response()->json($diffResult);

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
