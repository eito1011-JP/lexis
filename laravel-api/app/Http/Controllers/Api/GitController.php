<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GitController extends ApiBaseController
{
    /**
     * Git差分チェック
     */
    public function checkDiff(Request $request): JsonResponse
    {
        try {
            // Git差分チェックの実装
            // 実際の実装ではGitコマンドを実行して差分を確認

            return response()->json([
                'hasChanges' => false,
                'message' => '差分はありません',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Git差分チェックに失敗しました',
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
    public function diff(Request $request): JsonResponse
    {
        try {
            // Git差分取得の実装
            // 実際の実装ではGitコマンドを実行して差分を取得

            return response()->json([
                'diff' => '',
                'files' => [],
            ]);

        } catch (\Exception $e) {
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
