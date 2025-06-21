<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * カテゴリ一覧を取得
     */
    public function getCategories(Request $request): JsonResponse
    {
        try {
            $categories = DocumentCategory::select('id', 'name', 'slug', 'sidebar_label', 'position', 'description')
                ->orderBy('position')
                ->get();

            return response()->json([
                'categories' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'カテゴリ一覧の取得に失敗しました'
            ], 500);
        }
    }

    /**
     * カテゴリを作成
     */
    public function createCategory(Request $request): JsonResponse
    {
        try {
            $validator = \Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'slug' => 'required|string|unique:document_categories,slug',
                'sidebarLabel' => 'nullable|string|max:255',
                'position' => 'nullable|integer',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first()
                ], 400);
            }

            $category = DocumentCategory::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'sidebar_label' => $request->sidebarLabel,
                'position' => $request->position ?? 0,
                'description' => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'category' => $category
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'カテゴリの作成に失敗しました'
            ], 500);
        }
    }

    /**
     * カテゴリを更新
     */
    public function updateCategory(Request $request, $id): JsonResponse
    {
        try {
            $category = DocumentCategory::findOrFail($id);

            $validator = \Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'slug' => 'sometimes|required|string|unique:document_categories,slug,' . $id,
                'sidebarLabel' => 'nullable|string|max:255',
                'position' => 'nullable|integer',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first()
                ], 400);
            }

            $category->update([
                'name' => $request->name ?? $category->name,
                'slug' => $request->slug ?? $category->slug,
                'sidebar_label' => $request->sidebarLabel,
                'position' => $request->position ?? $category->position,
                'description' => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'category' => $category
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'カテゴリの更新に失敗しました'
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
                'message' => 'カテゴリを削除しました'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'カテゴリの削除に失敗しました'
            ], 500);
        }
    }

    /**
     * スラッグでカテゴリを取得
     */
    public function getCategoryBySlug(Request $request): JsonResponse
    {
        try {
            $slug = $request->query('slug');

            if (!$slug) {
                return response()->json([
                    'error' => '有効なslugが必要です'
                ], 400);
            }

            $category = DocumentCategory::where('slug', $slug)->first();

            if (!$category) {
                return response()->json([
                    'error' => 'カテゴリが見つかりません'
                ], 404);
            }

            return response()->json([
                'id' => $category->id,
                'slug' => $category->slug,
                'sidebarLabel' => $category->sidebar_label,
                'position' => $category->position,
                'description' => $category->description,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'カテゴリの取得に失敗しました'
            ], 500);
        }
    }

    /**
     * ドキュメント一覧を取得
     */
    public function getDocuments(Request $request): JsonResponse
    {
        try {
            $categorySlug = $request->query('category');

            $query = Document::with('category')
                ->select('id', 'category_id', 'sidebar_label', 'slug', 'is_public', 'status', 'last_edited_by', 'file_order');

            if ($categorySlug) {
                $query->whereHas('category', function ($q) use ($categorySlug) {
                    $q->where('slug', $categorySlug);
                });
            }

            $documents = $query->orderBy('file_order')->get();

            return response()->json([
                'documents' => $documents
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'ドキュメント一覧の取得に失敗しました'
            ], 500);
        }
    }

    /**
     * ドキュメントを作成
     */
    public function createDocument(Request $request): JsonResponse
    {
        try {
            $validator = \Validator::make($request->all(), [
                'category' => 'required|string',
                'label' => 'required|string|max:255',
                'content' => 'required|string',
                'isPublic' => 'boolean',
                'slug' => 'required|string|unique:documents,slug',
                'fileOrder' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first()
                ], 400);
            }

            // カテゴリを取得
            $category = DocumentCategory::where('slug', $request->category)->first();
            if (!$category) {
                return response()->json([
                    'error' => '指定されたカテゴリが見つかりません'
                ], 404);
            }

            $document = Document::create([
                'category_id' => $category->id,
                'sidebar_label' => $request->label,
                'slug' => $request->slug,
                'is_public' => $request->isPublic ?? false,
                'status' => 'draft',
                'last_edited_by' => $request->user['email'] ?? 'unknown',
                'file_order' => $request->fileOrder ?? 0,
            ]);

            return response()->json([
                'success' => true,
                'document' => $document
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'ドキュメントの作成に失敗しました'
            ], 500);
        }
    }

    /**
     * スラッグでドキュメントを取得
     */
    public function getDocumentBySlug(Request $request): JsonResponse
    {
        try {
            $slug = $request->query('slug');

            if (!$slug) {
                return response()->json([
                    'error' => '有効なslugが必要です'
                ], 400);
            }

            $document = Document::with('category')
                ->where('slug', $slug)
                ->first();

            if (!$document) {
                return response()->json([
                    'error' => 'ドキュメントが見つかりません'
                ], 404);
            }

            return response()->json([
                'document' => $document
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'ドキュメントの取得に失敗しました'
            ], 500);
        }
    }

    /**
     * ドキュメントを更新
     */
    public function updateDocument(Request $request, $id): JsonResponse
    {
        try {
            $document = Document::findOrFail($id);

            $validator = \Validator::make($request->all(), [
                'category' => 'sometimes|required|string',
                'label' => 'sometimes|required|string|max:255',
                'content' => 'sometimes|required|string',
                'isPublic' => 'boolean',
                'slug' => 'sometimes|required|string|unique:documents,slug,' . $id,
                'fileOrder' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()->first()
                ], 400);
            }

            // カテゴリが指定されている場合
            if ($request->has('category')) {
                $category = DocumentCategory::where('slug', $request->category)->first();
                if (!$category) {
                    return response()->json([
                        'error' => '指定されたカテゴリが見つかりません'
                    ], 404);
                }
                $document->category_id = $category->id;
            }

            $document->update([
                'sidebar_label' => $request->label ?? $document->sidebar_label,
                'slug' => $request->slug ?? $document->slug,
                'is_public' => $request->isPublic ?? $document->is_public,
                'last_edited_by' => $request->user['email'] ?? $document->last_edited_by,
                'file_order' => $request->fileOrder ?? $document->file_order,
            ]);

            return response()->json([
                'success' => true,
                'document' => $document
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'ドキュメントの更新に失敗しました'
            ], 500);
        }
    }

    /**
     * ドキュメントを削除
     */
    public function deleteDocument(Request $request, $id): JsonResponse
    {
        try {
            $document = Document::findOrFail($id);
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'ドキュメントを削除しました'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'ドキュメントの削除に失敗しました'
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

            if (!$slug) {
                return response()->json([
                    'error' => '有効なslugが必要です'
                ], 400);
            }

            $category = DocumentCategory::where('slug', $slug)->first();
            if (!$category) {
                return response()->json([
                    'error' => 'カテゴリが見つかりません'
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
                        'isDraft' => !$doc->is_public,
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
                'items' => $items
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'カテゴリコンテンツの取得に失敗しました'
            ], 500);
        }
    }
} 