<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\FixRequestStatus;
use App\Enums\PullRequestActivityAction;
use App\Http\Requests\Api\FixRequest\ApplyFixRequestRequest;
use App\Http\Requests\Api\FixRequest\GetFixRequestDiffRequest;
use App\Http\Requests\Api\PullRequest\SendFixRequest;
use App\Models\ActivityLogOnPullRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\FixRequest;
use App\Models\PullRequest;
use App\Services\MdFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixRequestController extends ApiBaseController
{
    /**
     * 修正リクエストの差分データを取得
     */
    public function getFixRequestDiff(GetFixRequestDiffRequest $request): JsonResponse
    {
        // 認証ユーザーかチェック
        $user = $this->getUserFromSession();

        if (! $user) {
            return response()->json([
                'error' => '認証が必要です。',
            ], 401);
        }

        $token = $request->validated('token');
        $pullRequestId = $request->validated('pull_request_id');

        try {
            // 現在のプルリクエストの変更差分を取得
            $currentPr = PullRequest::with(['userBranch'])
                ->findOrFail($pullRequestId);

            // tokenで修正リクエストのレコードをリレーション込みで取得（複数レコードあり得る）
            $fixRequests = FixRequest::with(['documentVersion', 'documentCategory', 'baseDocumentVersion', 'baseCategory'])
                ->where('token', $token)
                ->get();

            if ($fixRequests->isEmpty()) {
                return response()->json([
                    'error' => '指定されたトークンの修正リクエストが見つかりません。',
                ], 404);
            }

            // fix_requestのstatusを取得（全て同じstatusと仮定）
            $status = FixRequestStatus::from($fixRequests->first()->status);

            // fix_requestのデータ（statusに関わらず同じ）
            $fixRequestDocuments = $fixRequests->filter(function ($fixRequest) {
                return $fixRequest->document_version_id !== null;
            })->map(function ($fixRequest) {
                $document = $fixRequest->documentVersion;
                $document->base_document_version_id = $fixRequest->base_document_version_id;

                return $document;
            })->values();

            $fixRequestCategories = $fixRequests->filter(function ($fixRequest) {
                return $fixRequest->document_category_id !== null;
            })->map(function ($fixRequest) {
                $category = $fixRequest->documentCategory;
                $category->base_category_version_id = $fixRequest->base_category_version_id;

                return $category;
            })->values();

            // current_prのデータ（statusによって切り替え）
            $currentDocuments = collect();
            $currentCategories = collect();

            if ($status === FixRequestStatus::PENDING) {
                // 通常通りedit_start_versionsから
                $editStartVersions = $currentPr->userBranch->editStartVersions;
                $currentDocuments = $editStartVersions->where('target_type', EditStartVersionTargetType::DOCUMENT->value)->map(function ($esv) {
                    return $esv->currentDocumentVersion;
                })->filter();
                $currentCategories = $editStartVersions->where('target_type', EditStartVersionTargetType::CATEGORY->value)->map(function ($esv) {
                    return $esv->currentCategory;
                })->filter();
            } elseif ($status === FixRequestStatus::APPLIED) {
                // fix_requestのbase_document_version_id/base_category_version_idから取得
                $baseDocumentVersionIds = $fixRequests->filter(function ($fixRequest) {
                    return $fixRequest->base_document_version_id !== null;
                })->pluck('base_document_version_id')->toArray();
                $baseCategoryVersionIds = $fixRequests->filter(function ($fixRequest) {
                    return $fixRequest->base_category_version_id !== null;
                })->pluck('base_category_version_id')->toArray();

                if (! empty($baseDocumentVersionIds)) {
                    $currentDocuments = DocumentVersion::whereIn('id', $baseDocumentVersionIds)->withTrashed()->get();
                }
                if (! empty($baseCategoryVersionIds)) {
                    $currentCategories = DocumentCategory::whereIn('id', $baseCategoryVersionIds)->withTrashed()->get();
                }
            }

            // レスポンスデータを構築
            $response = [
                'status' => $status->value,
                'current_pr' => [
                    'documents' => $currentDocuments,
                    'categories' => $currentCategories,
                ],
                'fix_request' => [
                    'documents' => $fixRequestDocuments,
                    'categories' => $fixRequestCategories,
                ],
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'データの取得に失敗しました。',
            ], 500);
        }
    }

    /**
     * 修正リクエストを送信する
     */
    public function sendFixRequest(SendFixRequest $request, int $id): JsonResponse
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

            // 2. プルリクエストを取得
            $pullRequest = PullRequest::findOrFail($id);

            // 3. バリデーション済みデータを取得
            $validated = $request->validated();

            // 4. document_versionsとdocument_categoriesの一括取得
            $documentVersionIds = [];
            $documentCategoryIds = [];

            if (isset($validated['document_versions']) && is_array($validated['document_versions'])) {
                $documentVersionIds = array_column($validated['document_versions'], 'id');
            }

            if (isset($validated['document_categories']) && is_array($validated['document_categories'])) {
                $documentCategoryIds = array_column($validated['document_categories'], 'id');
            }

            // 一括でDocumentVersionsを取得
            $existingDocumentVersions = collect();
            if (! empty($documentVersionIds)) {
                $existingDocumentVersions = DocumentVersion::whereIn('id', $documentVersionIds)->get()->keyBy('id');
            }

            // 一括でDocumentCategoriesを取得
            $existingDocumentCategories = collect();
            if (! empty($documentCategoryIds)) {
                $existingDocumentCategories = DocumentCategory::whereIn('id', $documentCategoryIds)->get()->keyBy('id');
            }

            // 5. document_versionsをstatus='fix-request'でbulk insert
            $fixRequestsData = [];
            $newDocumentVersionsData = [];

            if (isset($validated['document_versions']) && is_array($validated['document_versions'])) {
                foreach ($validated['document_versions'] as $docVersion) {
                    $existingDocVersion = $existingDocumentVersions->get($docVersion['id']);

                    if (! $existingDocVersion) {
                        throw new \Exception('Document version not found: '.$docVersion['id']);
                    }

                    // 新しいドキュメントバージョンのデータを準備
                    $categoryParentPath = $existingDocVersion->category ? $existingDocVersion->category->parent_path : null;
                    $filePath = app(MdFileService::class)->generateFilePath($docVersion['slug'], $categoryParentPath);
                    $newDocumentVersionsData[] = [
                        'user_id' => $user->id,
                        'user_branch_id' => $existingDocVersion->user_branch_id,
                        'file_path' => $filePath,
                        'status' => DocumentStatus::FIX_REQUEST->value,
                        'content' => $docVersion['content'],
                        'slug' => $docVersion['slug'],
                        'category_id' => $existingDocVersion->category_id,
                        'sidebar_label' => $docVersion['sidebar_label'],
                        'file_order' => $existingDocVersion->file_order,
                        'is_public' => $existingDocVersion->is_public,
                    ];
                }
            }

            // 6. document_categoriesをstatus='fix-request'でbulk insert
            $newDocumentCategoriesData = [];
            if (isset($validated['document_categories']) && is_array($validated['document_categories'])) {
                foreach ($validated['document_categories'] as $docCategory) {
                    $existingDocCategory = $existingDocumentCategories->get($docCategory['id']);

                    if (! $existingDocCategory) {
                        throw new \Exception('Document category not found: '.$docCategory['id']);
                    }

                    // 新しいドキュメントカテゴリのデータを準備
                    $newDocumentCategoriesData[] = [
                        'slug' => $docCategory['slug'],
                        'sidebar_label' => $docCategory['sidebar_label'],
                        'position' => $existingDocCategory->position,
                        'description' => $docCategory['description'],
                        'status' => DocumentCategoryStatus::FIX_REQUEST->value,
                        'parent_id' => $existingDocCategory->parent_id,
                        'user_branch_id' => $existingDocCategory->user_branch_id,
                    ];
                }
            }

            // 7. 一括でDocumentVersionsを挿入
            $newDocumentVersionIds = collect();
            if (! empty($newDocumentVersionsData)) {
                // 挿入前の最大IDを取得
                $maxDocumentVersionId = DocumentVersion::max('id') ?? 0;

                DocumentVersion::insert($newDocumentVersionsData);

                // 挿入されたレコードのIDを取得（最大IDより大きいIDを取得）
                $newDocumentVersionIds = DocumentVersion::where('id', '>', $maxDocumentVersionId)
                    ->where('user_id', $user->id)
                    ->where('status', DocumentStatus::FIX_REQUEST->value)
                    ->orderBy('id', 'asc')
                    ->pluck('id');
            }

            // 8. 一括でDocumentCategoriesを挿入
            $newDocumentCategoryIds = collect();
            if (! empty($newDocumentCategoriesData)) {
                // 挿入前の最大IDを取得
                $maxDocumentCategoryId = DocumentCategory::max('id') ?? 0;

                DocumentCategory::insert($newDocumentCategoriesData);

                // 挿入されたレコードのIDを取得（最大IDより大きいIDを取得）
                $newDocumentCategoryIds = DocumentCategory::where('id', '>', $maxDocumentCategoryId)
                    ->where('status', DocumentCategoryStatus::FIX_REQUEST->value)
                    ->orderBy('id', 'asc')
                    ->pluck('id');
            }

            // 9. 修正リクエスト用のトークンを生成
            $fixRequestToken = \Illuminate\Support\Str::random(32);

            // 10. fix_requestsテーブル用のデータを準備
            // document_versions: base_document_version_idをセット
            foreach ($newDocumentVersionIds as $index => $newDocVersionId) {
                $baseDocVersionId = $documentVersionIds[$index] ?? null; // 送信元のid
                $fixRequestsData[] = [
                    'token' => $fixRequestToken,
                    'document_version_id' => $newDocVersionId,
                    'document_category_id' => null,
                    'base_document_version_id' => $baseDocVersionId,
                    'base_category_version_id' => null,
                    'user_id' => $user->id,
                    'pull_request_id' => $pullRequest->id,
                ];
            }

            // document_categories: base_category_version_idをセット
            foreach ($newDocumentCategoryIds as $index => $newDocCategoryId) {
                $baseCategoryId = $documentCategoryIds[$index] ?? null; // 送信元のid
                $fixRequestsData[] = [
                    'token' => $fixRequestToken,
                    'document_version_id' => null,
                    'document_category_id' => $newDocCategoryId,
                    'base_document_version_id' => null,
                    'base_category_version_id' => $baseCategoryId,
                    'user_id' => $user->id,
                    'pull_request_id' => $pullRequest->id,
                ];
            }

            // 11. fix_requestsテーブルにbulk insert
            if (! empty($fixRequestsData)) {
                FixRequest::insert($fixRequestsData);
            }

            // 7. activity_log_on_pull_requestsにアクティビティログを記録
            ActivityLogOnPullRequest::create([
                'user_id' => $user->id,
                'pull_request_id' => $pullRequest->id,
                'action' => PullRequestActivityAction::FIX_REQUEST_SENT->value,
                'fix_request_token' => $fixRequestToken,
            ]);

            DB::commit();

            return response()->json();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('修正リクエスト送信エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $id,
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'error' => '修正リクエストの送信に失敗しました',
            ], 500);
        }
    }

    /**
     * 修正リクエストを適用する
     */
    public function applyFixRequest(ApplyFixRequestRequest $request): JsonResponse
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

            // 2. バリデーション済みデータを取得
            $validated = $request->validated();
            $token = $validated['token'];

            // 3. fix_requestテーブルからtokenをwhereで絞り込みをかけてget
            $fixRequests = FixRequest::with(['documentVersion', 'documentCategory'])
                ->where('token', $token)
                ->get();

            if ($fixRequests->isEmpty()) {
                return response()->json([
                    'error' => '指定されたトークンの修正リクエストが見つかりません',
                ], 404);
            }

            // 4. fix_requestsのdocument_version_idとdocument_category_idに紐づくレコードを取得
            $documentVersionIds = $fixRequests->filter(function ($fixRequest) {
                return $fixRequest->document_version_id !== null;
            })->pluck('document_version_id')->toArray();

            $documentCategoryIds = $fixRequests->filter(function ($fixRequest) {
                return $fixRequest->document_category_id !== null;
            })->pluck('document_category_id')->toArray();

            $documentVersions = collect();
            if (! empty($documentVersionIds)) {
                $documentVersions = DocumentVersion::whereIn('id', $documentVersionIds)->get();
            }

            $documentCategories = collect();
            if (! empty($documentCategoryIds)) {
                $documentCategories = DocumentCategory::whereIn('id', $documentCategoryIds)->get();
            }

            // 5. edit_start_versionsにbulk insertするための変数を作成
            $editStartVersionsData = [];

            // ドキュメント版のedit_start_versions作成
            $now = now();
            foreach ($documentVersions as $docVersion) {
                $fixRequest = $fixRequests->firstWhere('document_version_id', $docVersion->id);
                $editStartVersionsData[] = [
                    'user_branch_id' => $docVersion->user_branch_id,
                    'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                    'original_version_id' => $fixRequest->base_document_version_id,
                    'current_version_id' => $docVersion->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // カテゴリ版のedit_start_versions作成
            foreach ($documentCategories as $docCategory) {
                $fixRequest = $fixRequests->firstWhere('document_category_id', $docCategory->id);
                $editStartVersionsData[] = [
                    'user_branch_id' => $docCategory->user_branch_id,
                    'target_type' => EditStartVersionTargetType::CATEGORY->value,
                    'original_version_id' => $fixRequest->base_category_version_id,
                    'current_version_id' => $docCategory->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // 6. edit_start_versionsにbulk insert
            if (! empty($editStartVersionsData)) {
                EditStartVersion::insert($editStartVersionsData);
            }

            // 6.5. fix_requestsのstatusをappliedに一括更新
            $fixRequestIds = $fixRequests->pluck('id')->toArray();
            if (! empty($fixRequestIds)) {
                FixRequest::whereIn('id', $fixRequestIds)->update(['status' => 'applied']);
            }

            // 7. base_document_version_idとbase_category_version_idに紐づくdocument_versionsとdocument_categoriesを取得
            $baseDocumentVersionIds = $fixRequests->filter(function ($fixRequest) {
                return $fixRequest->base_document_version_id !== null;
            })->pluck('base_document_version_id')->unique()->toArray();

            $baseCategoryVersionIds = $fixRequests->filter(function ($fixRequest) {
                return $fixRequest->base_category_version_id !== null;
            })->pluck('base_category_version_id')->unique()->toArray();

            // 元のedit_start_versionsを論理削除
            if (! empty($baseDocumentVersionIds)) {
                EditStartVersion::where('target_type', EditStartVersionTargetType::DOCUMENT->value)
                    ->whereIn('current_version_id', $baseDocumentVersionIds)
                    ->delete();
            }

            if (! empty($baseCategoryVersionIds)) {
                EditStartVersion::where('target_type', EditStartVersionTargetType::CATEGORY->value)
                    ->whereIn('current_version_id', $baseCategoryVersionIds)
                    ->delete();
            }

            // 8. activity_log_on_pull_requestsにアクティビティログを記録（fix_request_idをセット）
            ActivityLogOnPullRequest::create([
                'user_id' => $user->id,
                'pull_request_id' => $fixRequests->first()->pull_request_id,
                'comment_id' => null,
                'reviewer_id' => null,
                'action' => PullRequestActivityAction::FIX_REQUEST_APPLIED->value,
                'old_pull_request_title' => null,
                'new_pull_request_title' => null,
                'fix_request_token' => $fixRequests->first()->token,
                'fix_request_id' => $fixRequests->first()->id,
            ]);

            DB::commit();

            return response()->json();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('修正リクエスト適用エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'token' => $token ?? null,
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'error' => '修正リクエストの適用に失敗しました',
            ], 500);
        }
    }
}
