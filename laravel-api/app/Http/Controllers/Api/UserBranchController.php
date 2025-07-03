<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentCategoryPrStatus;
use App\Http\Requests\CreatePullRequestRequest;
use App\Http\Requests\FetchDiffRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\PullRequest;
use App\Models\PullRequestReviewer;
use App\Models\User;
use App\Services\GitService;
use App\Services\MarkdownFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserBranchController extends ApiBaseController
{
    protected GitService $gitService;

    protected MarkdownFileService $markdownFileService;

    public function __construct(GitService $gitService, MarkdownFileService $markdownFileService)
    {
        $this->gitService = $gitService;
        $this->markdownFileService = $markdownFileService;
    }

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
    public function createPullRequest(CreatePullRequestRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // 1. 認証ユーザーか確認
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証されていません',
                ], 401);
            }

            // 2. diffをidとuser_branch_idで絞り込んでfetch
            $userBranch = $user->userBranches()
                ->active()
                ->where('id', $request->user_branch_id)
                ->where('pr_status', DocumentCategoryPrStatus::NONE->value)
                ->first();

            if (! $userBranch) {
                return response()->json([
                    'error' => 'ユーザーブランチが見つかりません',
                ], 404);
            }

            // 3. diffアイテムを取得
            $diffItems = $request->diff_items;
            $documentVersions = collect();
            $documentCategories = collect();

            foreach ($diffItems as $item) {
                if ($item['type'] === 'document') {
                    $documentVersion = DocumentVersion::where('id', $item['id'])
                        ->where('user_branch_id', $request->user_branch_id)
                        ->first();

                    if ($documentVersion) {
                        $documentVersions->push($documentVersion);
                    }
                } elseif ($item['type'] === 'category') {
                    $documentCategory = DocumentCategory::where('id', $item['id'])
                        ->whereHas('editStartVersions', function ($query) use ($request) {
                            $query->where('user_branch_id', $request->user_branch_id);
                        })
                        ->first();

                    if ($documentCategory) {
                        $documentCategories->push($documentCategory);
                    }
                }
            }

            // 4. 2でfetchしたデータを元にmdファイルをdocsディレクトリ下に作成
            $commitMessage = 'Update documents: '.$request->title;

            foreach ($documentVersions as $documentVersion) {
                $markdownContent = $this->markdownFileService->createDocumentMarkdown($documentVersion);
                $filePath = $this->markdownFileService->generateFilePath($documentVersion->slug, $documentVersion->category_path);

                $this->gitService->createOrUpdateFile(
                    $filePath,
                    $markdownContent,
                    $userBranch->branch_name,
                    $commitMessage
                );
            }

            foreach ($documentCategories as $documentCategory) {
                $markdownContent = $this->markdownFileService->createCategoryMarkdown($documentCategory);
                $filePath = $this->markdownFileService->generateFilePath($documentCategory->slug, $documentCategory->parent_path);

                $this->gitService->createOrUpdateFile(
                    $filePath,
                    $markdownContent,
                    $userBranch->branch_name,
                    $commitMessage
                );

                // カテゴリJSONファイルも作成
                $categoryJsonContent = $this->markdownFileService->createCategoryJson($documentCategory);
                $categoryJsonPath = $this->markdownFileService->generateCategoryFilePath($documentCategory->slug, $documentCategory->parent_path);

                $this->gitService->createOrUpdateFile(
                    $categoryJsonPath,
                    $categoryJsonContent,
                    $userBranch->branch_name,
                    $commitMessage
                );
            }

            // 5. レビュアーのGitHubユーザー名を取得
            $reviewerUsernames = [];
            $reviewerUserIds = [];

            if ($request->reviewers) {
                // 一括でレビュアーユーザーを取得
                $reviewerUsers = User::whereIn('email', $request->reviewers)->get();

                foreach ($reviewerUsers as $reviewerUser) {
                    $reviewerUserIds[] = $reviewerUser->id;
                    // GitHubユーザー名がある場合は追加（今回は仮でemailのローカル部分を使用）
                    $reviewerUsernames[] = explode('@', $reviewerUser->email)[0];
                }
            }

            // 6. GitHub APIでプルリクエストを作成
            $prResult = $this->gitService->createPullRequest(
                $userBranch->branch_name,
                $request->title,
                $request->body ?? '',
                $reviewerUsernames
            );

            // 7. pull_requestsテーブルにデータを保存
            $pullRequest = PullRequest::create([
                'user_branch_id' => $userBranch->id,
                'title' => $request->title,
                'description' => $request->body,
                'github_url' => $prResult['pr_url'],
                'status' => 'opened',
            ]);

            // 8. pull_request_reviewersテーブルにレビュアーを保存
            if (! empty($reviewerUserIds)) {
                $reviewerData = array_map(function ($reviewerUserId) use ($pullRequest) {
                    return [
                        'pull_request_id' => $pullRequest->id,
                        'user_id' => $reviewerUserId,
                    ];
                }, $reviewerUserIds);

                PullRequestReviewer::insert($reviewerData);
            }

            // 9. ユーザーブランチのステータスを更新
            $userBranch->update([
                'pr_status' => DocumentCategoryPrStatus::CREATED->value,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'プルリクエストを作成しました',
                'pr_url' => $prResult['pr_url'],
                'pr_number' => $prResult['pr_number'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('プルリクエスト作成エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

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
