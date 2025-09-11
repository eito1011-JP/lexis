<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Consts\Flag;
use App\Enums\DocumentCategoryStatus;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Http\Requests\CreateDocumentCategoryRequest;
use App\Http\Requests\DeleteDocumentCategoryRequest;
use App\Http\Requests\UpdateDocumentCategoryRequest;
use App\Http\Requests\Api\DocumentCategory\FetchCategoriesRequest;
use App\UseCases\DocumentCategory\FetchCategoriesUseCase;
use App\UseCases\DocumentCategory\CreateDocumentCategoryUseCase;
use App\Dto\UseCase\DocumentCategory\CreateDocumentCategoryDto;
use App\Dto\UseCase\DocumentCategory\FetchCategoriesDto;
use App\Exceptions\AuthenticationException;
use Http\Discovery\Exception\NotFoundException;
use App\Exceptions\DuplicateExecutionException;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Models\PullRequestEditSession;
use App\Models\PullRequestEditSessionDiff;
use App\Services\DocumentCategoryService;
use App\Services\UserBranchService;
use Exception;
use Illuminate\Http\Request;
use Psr\Log\LogLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class DocumentCategoryController extends ApiBaseController
{
    protected $documentCategoryService;

    protected $userBranchService;

    public function __construct(
        DocumentCategoryService $documentCategoryService, 
        UserBranchService $userBranchService
    ) {
        $this->documentCategoryService = $documentCategoryService;
        $this->userBranchService = $userBranchService;
    }

    /**
     * カテゴリ一覧を取得
     */
    public function fetchCategories(FetchCategoriesRequest $request, FetchCategoriesUseCase $useCase): JsonResponse
    {
        try {
            // 認証チェック
            $user = $this->user();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            // DTOを作成してUseCaseを実行
            $dto = FetchCategoriesDto::fromArray($request->validated());
            $categories = $useCase->execute($dto, $user);

            return response()->json([
                'categories' => $categories,
            ]);

        } catch (\Exception $e) {
            Log::error('カテゴリ一覧の取得に失敗しました', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'カテゴリ一覧の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * カテゴリを作成
     */
    public function createCategory(CreateDocumentCategoryRequest $request, CreateDocumentCategoryUseCase $useCase): JsonResponse
    {
        try {
            // 認証チェック
            $user = $this->user();

            if (! $user) {
                return $this->sendError(
                    ErrorType::CODE_AUTHENTICATION_FAILED,
                    __('errors.MSG_AUTHENTICATION_FAILED'),
                    ErrorType::STATUS_AUTHENTICATION_FAILED,
                );
            }

            // DTOを作成
            $dto = CreateDocumentCategoryDto::fromRequest($request->validated());

            // UseCaseを実行
            $useCase->execute($dto, $user);

            return response()->json();  
        } catch (AuthenticationException) {
            return $this->sendError(
                ErrorType::CODE_AUTHENTICATION_FAILED,
                __('errors.MSG_AUTHENTICATION_FAILED'),
                ErrorType::STATUS_AUTHENTICATION_FAILED,
            );
        } catch (NotFoundException) {
            return $this->sendError(
                ErrorType::CODE_NOT_FOUND,
                __('errors.MSG_NOT_FOUND'),
                ErrorType::STATUS_NOT_FOUND,
            );
        } catch (DuplicateExecutionException) {
            return $this->sendError(
                ErrorType::CODE_DUPLICATE_EXECUTION,
                __('errors.MSG_DUPLICATE_EXECUTION'),
                ErrorType::STATUS_DUPLICATE_EXECUTION,
            );
        } catch (Exception) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }
    }

    /**
     * カテゴリを更新
     */
    public function updateCategory(UpdateDocumentCategoryRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = $this->user();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch($user, $request->edit_pull_request_id);

            // 編集セッションIDを取得
            $pullRequestEditSessionId = $this->getPullRequestEditSessionId($user, $request->edit_pull_request_id);

            $categoryPath = array_filter(explode('/', $request->category_path));
            $parentCategoryId = $this->documentCategoryService->getIdFromPath(implode('/', $categoryPath));

            $existingCategory = DocumentCategory::where('parent_id', $parentCategoryId)->where('slug', $request->slug)->first();

            if (! $existingCategory) {
                return response()->json([
                    'error' => '更新対象のカテゴリが見つかりません',
                ], 404);
            }

            // positionの正規化
            $position = $this->documentCategoryService->normalizePosition(
                $request->position,
                $existingCategory->parent_id
            );

            EditStartVersion::where('current_version_id', $existingCategory->id)
                ->where('target_type', EditStartVersionTargetType::CATEGORY->value)
                ->first()
                ->delete();

            $existingCategory->delete();

            $newCategory = DocumentCategory::create([
                'slug' => $request->slug,
                'sidebar_label' => $request->sidebar_label,
                'position' => $position,
                'description' => $request->description,
                'user_branch_id' => $userBranchId,
                'pull_request_edit_session_id' => $pullRequestEditSessionId,
                'parent_id' => $existingCategory->parent_id,
            ]);

            // 編集開始バージョンの作成
            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => 'category',
                'original_version_id' => $existingCategory->id,
                'current_version_id' => $newCategory->id,
            ]);

            // プルリクエスト編集セッション差分の処理
            if ($pullRequestEditSessionId) {
                PullRequestEditSessionDiff::updateOrCreate(
                    [
                        'pull_request_edit_session_id' => $pullRequestEditSessionId,
                        'target_type' => EditStartVersionTargetType::CATEGORY->value,
                        'current_version_id' => $existingCategory->id,
                    ],
                    [
                        'current_version_id' => $newCategory->id,
                        'diff_type' => 'updated',
                    ]
                );
            }

            DB::commit();

            return response()->json();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('カテゴリの更新に失敗しました', [
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'カテゴリの更新に失敗しました',
            ], 500);
        }
    }

    /**
     * カテゴリを削除
     */
    public function deleteCategory(DeleteDocumentCategoryRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // 認証チェック
            $user = $this->user();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            // ユーザーのアクティブブランチ確認
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch($user, $request->edit_pull_request_id);

            $pullRequestEditSessionId = $this->getPullRequestEditSessionId($user, $request->edit_pull_request_id, $request->pull_request_edit_token);
            // カテゴリパスの取得と処理
            $pathParts = array_filter(explode('/', $request->category_path_with_slug));
            $slug = array_pop($pathParts);
            $categoryPath = implode('/', $pathParts);
            $parentCategoryId = $this->documentCategoryService->getIdFromPath($categoryPath);

            // 削除対象のルートカテゴリの存在確認
            $rootCategory = DocumentCategory::where('slug', $slug)
                ->where('parent_id', $parentCategoryId)
                ->where(function ($query) use ($userBranchId) {
                    $query->where('status', DocumentCategoryStatus::MERGED->value)
                        ->orWhere(function ($subQuery) use ($userBranchId) {
                            $subQuery->where('status', '!=', DocumentCategoryStatus::MERGED->value)
                                ->where('user_branch_id', $userBranchId);
                        });
                })
                ->first();

            if (! $rootCategory) {
                return response()->json([
                    'error' => 'カテゴリが見つかりません',
                ], 404);
            }

            // 再帰CTEを使用して削除対象のカテゴリIDを取得
            $categoryIds = DB::select('
                WITH RECURSIVE tree AS (
                    SELECT id FROM document_categories
                    WHERE slug = ? AND parent_id = ?
                    AND (status = ? OR (status != ? AND user_branch_id = ?))
                    
                    UNION ALL
                    
                    SELECT dc.id
                    FROM document_categories dc
                    INNER JOIN tree t ON dc.parent_id = t.id
                    WHERE (dc.status = ? OR (dc.status != ? AND dc.user_branch_id = ?))
                )
                SELECT id FROM tree
            ', [$slug, $parentCategoryId, DocumentCategoryStatus::MERGED->value, DocumentCategoryStatus::MERGED->value, $userBranchId, DocumentCategoryStatus::MERGED->value, DocumentCategoryStatus::MERGED->value, $userBranchId]);

            $categoryIdArray = array_column($categoryIds, 'id');

            if (empty($categoryIdArray)) {
                return response()->json([
                    'error' => 'カテゴリが見つかりません',
                ], 404);
            }

            // 削除対象のカテゴリを取得
            $categories = DocumentCategory::whereIn('id', $categoryIdArray)->get();

            // 削除対象のドキュメントを取得
            $documents = DocumentVersion::whereIn('category_id', $categoryIdArray)
                ->where(function ($query) use ($userBranchId) {
                    $query->where('status', DocumentStatus::MERGED->value)
                        ->orWhere(function ($subQuery) use ($userBranchId) {
                            $subQuery->where('status', '!=', DocumentStatus::MERGED->value)
                                ->where('user_branch_id', $userBranchId);
                        });
                })
                ->get();

            $now = now();

            // 既存のedit_start_versionsレコードを取得
            $existingEditVersions = EditStartVersion::where('target_type', 'category')
                ->whereIn('original_version_id', $categoryIdArray)
                ->get()
                ->keyBy('original_version_id');

            // 論理削除前の件数を取得
            $beforeCount = DocumentCategory::whereIn('id', $categoryIdArray)
                ->where('is_deleted', Flag::FALSE)
                ->count();

            // カテゴリを一括で論理削除(ここは消えている)
            DocumentCategory::whereIn('id', $categoryIdArray)
                ->update([
                    'is_deleted' => Flag::TRUE,
                    'deleted_at' => $now,
                ]);

            // ドキュメントを論理削除
            if ($documents->isNotEmpty()) {
                DocumentVersion::whereIn('id', $documents->pluck('id'))
                    ->update([
                        'is_deleted' => Flag::TRUE,
                        'deleted_at' => $now,
                    ]);
            }

            // 削除されたカテゴリの新しいバージョンをバルク作成
            $newCategoryData = [];

            foreach ($categories as $category) {
                $newCategoryData[] = [
                    'sidebar_label' => $category->sidebar_label,
                    'slug' => $category->slug,
                    'parent_id' => $category->parent_id,
                    'position' => $category->position,
                    'description' => $category->description,
                    'user_branch_id' => $userBranchId,
                    'is_deleted' => Flag::TRUE,
                    'deleted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // バルクインサートを実行
            DocumentCategory::insert($newCategoryData);

            // 挿入されたレコードのIDを取得
            $insertedCategoryIds = DocumentCategory::where('user_branch_id', $userBranchId)
                ->onlyTrashed()
                ->orderBy('id', 'desc')
                ->limit(count($newCategoryData))
                ->pluck('id')
                ->reverse()
                ->values()
                ->toArray();

            // カテゴリのマッピングを作成
            foreach ($categories as $index => $category) {
                $deletedCategory[$category->id] = $insertedCategoryIds[$index];
            }

            // 削除されたドキュメントの新しいバージョンをバルク作成
            if ($documents->isNotEmpty()) {
                $newDocumentData = [];
                foreach ($documents as $document) {
                    $newDocumentData[] = [
                        'user_id' => $user->id,
                        'user_branch_id' => $userBranchId,
                        'file_path' => $document->file_path,
                        'status' => $document->status,
                        'content' => $document->content,
                        'slug' => $document->slug,
                        'sidebar_label' => $document->sidebar_label,
                        'file_order' => $document->file_order,
                        'last_edited_by' => $user->email,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'is_deleted' => Flag::TRUE,
                        'is_public' => $document->is_public,
                        'category_id' => $document->category_id,
                    ];
                }

                // バルクインサートを実行
                DocumentVersion::insert($newDocumentData);
            }

            // edit_start_versionsの更新・作成
            $editVersionsToCreate = [];
            $existingEditVersionIds = [];

            foreach ($categories as $index => $category) {
                $newCategoryId = $insertedCategoryIds[$index];

                if (isset($existingEditVersions[$category->id])) {
                    // 既存レコードのIDを収集
                    $existingEditVersionIds[] = $existingEditVersions[$category->id]->id;
                }

                // 全ての更新操作を表す新しいレコードを作成
                $editVersionsToCreate[] = [
                    'user_branch_id' => $userBranchId,
                    'target_type' => EditStartVersionTargetType::CATEGORY->value,
                    'original_version_id' => $category->id,
                    'current_version_id' => $newCategoryId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // 既存のedit_start_versionsを一括で論理削除
            if (! empty($existingEditVersionIds)) {
                EditStartVersion::whereIn('id', $existingEditVersionIds)
                    ->update([
                        'is_deleted' => Flag::TRUE,
                        'deleted_at' => $now,
                    ]);
            }

            // バルク作成
            if (! empty($editVersionsToCreate)) {
                EditStartVersion::insert($editVersionsToCreate);
            }

            // プルリクエスト編集セッション差分の処理
            if ($pullRequestEditSessionId) {
                foreach ($categories as $index => $category) {
                    $newCategoryId = $insertedCategoryIds[$index];
                    PullRequestEditSessionDiff::updateOrCreate(
                        [
                            'pull_request_edit_session_id' => $pullRequestEditSessionId,
                            'target_type' => EditStartVersionTargetType::CATEGORY->value,
                            'current_version_id' => $category->id,
                        ],
                        [
                            'current_version_id' => $newCategoryId,
                            'diff_type' => 'deleted',
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('カテゴリの削除に失敗しました', [
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return response()->json([
                'error' => 'カテゴリの削除に失敗しました',
            ], 500);
        }
    }

    /**
     * カテゴリコンテンツを取得
     */
    public function getCategoryContents(Request $request): JsonResponse
    {
        try {
            $slug = $request->query('slug');

            if (! $slug) {
                return response()->json([
                    'error' => '有効なslugが必要です',
                ], 400);
            }

            $category = DocumentCategory::where('slug', $slug)->first();
            if (! $category) {
                return response()->json([
                    'error' => 'カテゴリが見つかりません',
                ], 404);
            }

            // ドキュメントとサブカテゴリを取得
            $documents = DocumentVersion::where('category_id', $category->id)
                ->select('id', 'sidebar_label as name', 'slug', 'is_public')
                ->get()
                ->map(function ($doc) {
                    return [
                        'name' => $doc->name,
                        'path' => $doc->slug,
                        'type' => 'document',
                        'label' => $doc->name,
                        'isDraft' => ! $doc->is_public,
                    ];
                });

            $subCategories = DocumentCategory::where('parent_id', $category->id)
                ->select('id', 'name', 'slug')
                ->get()
                ->map(function ($cat) {
                    return [
                        'name' => $cat->name,
                        'path' => $cat->slug,
                        'type' => 'category',
                    ];
                });

            $items = $documents->concat($subCategories);

            return response()->json([
                'items' => $items,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'カテゴリコンテンツの取得に失敗しました',
            ], 500);
        }
    }

    /**
     * プルリクエスト編集セッションIDを取得する
     */
    private function getPullRequestEditSessionId($user, ?int $editPullRequestId): ?int
    {
        if (! $editPullRequestId) {
            return null;
        }

        // 指定されたプルリクエストで現在進行中の編集セッションを取得
        $editSession = PullRequestEditSession::where('pull_request_id', $editPullRequestId)
            ->where('user_id', $user->id)
            ->whereNull('finished_at')
            ->orderBy('created_at', 'desc')
            ->first();

        return $editSession?->id;
    }
}
