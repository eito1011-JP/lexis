<?php

namespace App\Http\Controllers\Api;

use App\Consts\Flag;
use App\Http\Requests\CreateDocumentCategoryRequest;
use App\Http\Requests\DeleteDocumentCategoryRequest;
use App\Http\Requests\GetDocumentCategoriesRequest;
use App\Http\Requests\UpdateDocumentCategoryRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\EditStartVersion;
use App\Services\DocumentCategoryService;
use App\Services\UserBranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentCategoryController extends ApiBaseController
{
    protected $documentCategoryService;

    protected $userBranchService;

    public function __construct(DocumentCategoryService $documentCategoryService, UserBranchService $userBranchService)
    {
        $this->documentCategoryService = $documentCategoryService;
        $this->userBranchService = $userBranchService;
    }

    /**
     * カテゴリ一覧を取得
     */
    public function getCategoryByPath(GetDocumentCategoriesRequest $request): JsonResponse
    {
        try {
            // カテゴリパスの取得と処理
            $categoryPath = array_filter(explode('/', $request->category_path));

            $categoryId = DocumentCategory::getIdFromPath($categoryPath);

            $documentCategory = DocumentCategory::find($categoryId);

            return response()->json([
                'categories' => $documentCategory,
            ]);

        } catch (\Exception $e) {
            Log::error('カテゴリ詳細の取得に失敗しました', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'カテゴリ詳細の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * カテゴリを作成
     */
    public function createCategory(CreateDocumentCategoryRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // 認証チェック
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            // カテゴリパスの取得と処理
            $categoryPath = array_filter(explode('/', $request->slug));

            // カテゴリIDを取得（パスから）
            $currentCategoryId = DocumentCategory::getIdFromPath($categoryPath);

            $existingCategory = DocumentCategory::where('slug', $request->slug)
                ->where('parent_id', $currentCategoryId)
                ->first();

            if ($existingCategory) {
                return response()->json([
                    'error' => 'このslugのカテゴリは既に存在します',
                ], 409);
            }

            $position = $this->documentCategoryService->normalizePosition(
                $request->position,
                $currentCategoryId
            );

            $category = DocumentCategory::create([
                'slug' => $request->slug,
                'sidebar_label' => $request->sidebar_label,
                'position' => $position,
                'description' => $request->description,
                'user_branch_id' => $user['userBranchId'],
                'parent_id' => $currentCategoryId,
            ]);

            DB::commit();

            return response()->json([
                'id' => $category->id,
                'slug' => $category->slug,
                'label' => $category->sidebar_label,
                'position' => $category->position,
                'description' => $category->description,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('カテゴリ作成エラー', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Failed to create category',
            ], 500);
        }
    }

    /**
     * カテゴリを更新
     */
    public function updateCategory(UpdateDocumentCategoryRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch($user->id);

            $path = $request->route('category_path');
            $categoryPath = array_filter(explode('/', $path));
            $categoryId = DocumentCategory::getIdFromPath($categoryPath);

            $existingCategory = DocumentCategory::find($categoryId);

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

            $existingCategory->delete();

            $newCategory = DocumentCategory::create([
                'slug' => $request->slug,
                'sidebar_label' => $request->sidebar_label,
                'position' => $position,
                'description' => $request->description,
                'user_branch_id' => $userBranchId,
                'parent_id' => $existingCategory->parent_id,
            ]);

            // 編集開始バージョンの作成
            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => 'category',
                'original_version_id' => $existingCategory->id,
                'current_version_id' => $newCategory->id,
            ]);

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
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            // ユーザーのアクティブブランチ確認
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch($user->id);

            // カテゴリツリーを取得
            $categoryTree = $this->documentCategoryService->getCategoryTreeFromSlug($request->category_path, $userBranchId);
            $categories = $categoryTree['categories'];
            $documents = $categoryTree['documents'];

            if (empty($categories)) {
                return response()->json([
                    'error' => 'カテゴリが見つかりません',
                ], 404);
            }

            $now = now();

            // 削除対象のカテゴリが既にedit_start_versionsに存在する場合、そのレコードを更新
            $existingEditVersions = [];
            foreach ($categories as $category) {
                $existingVersion = EditStartVersion::where('target_type', 'category')
                    ->where('original_version_id', $category->id)
                    ->first();

                if ($existingVersion) {
                    $existingEditVersions[$category->id] = $existingVersion;
                }
            }

            // document_categoriesをis_deleted=1に更新
            foreach ($categories as $category) {
                $category->update([
                    'is_deleted' => Flag::TRUE,
                    'updated_at' => $now,
                ]);
            }

            // document_versionsをis_deleted=1に更新
            if ($documents->isNotEmpty()) {
                foreach ($documents as $document) {
                    $document->update([
                        'is_deleted' => Flag::TRUE,
                        'updated_at' => $now,
                    ]);
                }
            }

            // ブランチ管理のために削除したcategoriesを追加
            $insertedCategoryIds = [];
            foreach ($categories as $category) {
                $newCategory = DocumentCategory::create([
                    'sidebar_label' => $category->sidebar_label,
                    'slug' => $category->slug,
                    'parent_id' => $category->parent_id,
                    'user_branch_id' => $userBranchId,
                    'is_deleted' => Flag::TRUE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $insertedCategoryIds[] = $newCategory->id;
            }

            // ブランチ管理のために削除したdocument_versionsを追加
            if ($documents->isNotEmpty()) {
                foreach ($documents as $document) {
                    Document::create([
                        'user_id' => $user['id'],
                        'user_branch_id' => $userBranchId,
                        'id' => $document->id,
                        'file_path' => $document->file_path,
                        'status' => $document->status,
                        'content' => $document->content,
                        'slug' => $document->slug,
                        'sidebar_label' => $document->sidebar_label,
                        'file_order' => $document->file_order,
                        'last_edited_by' => $user['email'],
                        'created_at' => $now,
                        'updated_at' => $now,
                        'is_deleted' => Flag::TRUE,
                        'is_public' => $document->is_public,
                        'category_id' => $document->category_id,
                    ]);
                }
            }

            // edit_start_versionsに削除履歴を記録
            foreach ($categories as $index => $category) {
                $newCategoryId = $insertedCategoryIds[$index];

                // 既存のedit_start_versionsレコードがある場合は更新、ない場合は新規作成
                if (isset($existingEditVersions[$category->id])) {
                    $existingEditVersions[$category->id]->update([
                        'current_version_id' => $newCategoryId,
                        'updated_at' => $now,
                    ]);
                } else {
                    EditStartVersion::create([
                        'user_branch_id' => $userBranchId,
                        'target_type' => 'category',
                        'original_version_id' => $category->id,
                        'current_version_id' => $newCategoryId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            DB::commit();

            return response()->json();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('カテゴリの削除に失敗しました', [
                'error' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
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
            $documents = Document::where('category_id', $category->id)
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
}
