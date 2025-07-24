<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Enums\PullRequestActivityAction;
use App\Http\Requests\Api\FixRequest\GetFixRequestDiffRequest;
use App\Http\Requests\Api\PullRequest\SendFixRequest;
use App\Models\ActivityLogOnPullRequest;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
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

            // fix_requestのドキュメントとカテゴリを分離
            $fixRequestDocuments = $fixRequests->filter(function ($fixRequest) {
                return $fixRequest->document_version_id !== null;
            })->map(function ($fixRequest) {
                $document = $fixRequest->documentVersion;
                if ($document) {
                    $documentArray = $document->toArray();
                    $documentArray['base_document_version_id'] = $fixRequest->base_document_version_id;

                    return $documentArray;
                }

                return null;
            })->filter();

            $fixRequestCategories = $fixRequests->filter(function ($fixRequest) {
                return $fixRequest->document_category_id !== null;
            })->map(function ($fixRequest) {
                $category = $fixRequest->documentCategory;
                if ($category) {
                    $categoryArray = $category->toArray();
                    $categoryArray['base_category_version_id'] = $fixRequest->base_category_version_id;

                    return $categoryArray;
                }

                return null;
            })->filter();

            // 最新のedit_start_versionsからdocumentとcategoryを取得
            $editStartVersions = $currentPr->userBranch->editStartVersions;
            $currentDocuments = $editStartVersions->where('target_type', EditStartVersionTargetType::DOCUMENT->value)->map(function ($esv) {
                return $esv->currentDocumentVersion;
            });
            $currentCategories = $editStartVersions->where('target_type', EditStartVersionTargetType::CATEGORY->value)->map(function ($esv) {
                return $esv->currentCategory;
            });

            // レスポンスデータを構築
            $response = [
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
}
