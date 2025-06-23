<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CreateDocumentCategoryRequest;
use App\Http\Requests\GetDocumentCategoriesRequest;
use App\Http\Requests\UpdateDocumentCategoryRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocumentCategoryController extends ApiBaseController
{
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
        try {
            // 認証チェック
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            $slug = $request->slug;
            $sidebarLabel = $request->sidebarLabel;
            $position = $request->position ?? 0;
            $description = $request->description;

            // カテゴリパスの取得と処理
            $categoryPath = array_filter(explode('/', $request->slug));

            // カテゴリIDを取得（パスから）
            $currentCategoryId = DocumentCategory::getIdFromPath($categoryPath);

            $existingCategory = DocumentCategory::where('slug', $slug)
                ->where('parent_id', $currentCategoryId)
                ->first();

            if ($existingCategory) {
                return response()->json([
                    'error' => 'このslugのカテゴリは既に存在します',
                ], 409);
            }

            $category = DocumentCategory::create([
                'slug' => $slug,
                'sidebar_label' => $sidebarLabel,
                'position' => $position,
                'description' => $description,
                'user_branch_id' => $user['userBranchId'],
                'parent_id' => $currentCategoryId,
            ]);

            return response()->json([
                'id' => $category->id,
                'slug' => $category->slug,
                'label' => $category->sidebar_label,
                'position' => $category->position,
                'description' => $category->description,
            ]);
        } catch (\Exception $e) {
            Log::error('カテゴリ作成エラー', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Failed to create category',
            ], 500);
        }
    }

    /**
     * カテゴリを更新
     */
    public function updateCategory(UpdateDocumentCategoryRequest $request, $id): JsonResponse
    {
        try {
            $category = DocumentCategory::findOrFail($id);

            $category->update([
                'name' => $request->name ?? $category->name,
                'slug' => $request->slug ?? $category->slug,
                'sidebar_label' => $request->sidebarLabel,
                'position' => $request->position ?? $category->position,
                'description' => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'category' => $category,
            ]);

        } catch (\Exception $e) {
            Log::error('カテゴリの更新に失敗しました', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'カテゴリの更新に失敗しました',
            ], 500);
        }
    }

    /**
     * カテゴリを削除
     */
    public function deleteCategory(Request $request, $id): JsonResponse
    {
        try {
            $category = DocumentCategory::findOrFail($id);
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'カテゴリを削除しました',
            ]);

        } catch (\Exception $e) {
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
