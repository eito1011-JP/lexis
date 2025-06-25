<?php

namespace App\Http\Controllers\Api;

use App\Constants\DocumentPathConstants;
use App\Consts\Flag;
use App\Enums\DocumentStatus;
use App\Enums\EditStartVersionTargetType;
use App\Http\Requests\Api\Document\CreateDocumentRequest;
use App\Http\Requests\Api\Document\DeleteDocumentRequest;
use App\Http\Requests\Api\Document\GetDocumentByCategoryPathRequest;
use App\Http\Requests\Api\Document\GetDocumentsRequest;
use App\Http\Requests\Api\Document\UpdateDocumentRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\EditStartVersion;
use App\Services\DocumentService;
use App\Services\UserBranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentController extends ApiBaseController
{
    protected DocumentService $documentService;

    protected UserBranchService $userBranchService;

    public function __construct(DocumentService $documentService, UserBranchService $userBranchService)
    {
        $this->documentService = $documentService;
        $this->userBranchService = $userBranchService;
    }

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
                'categories' => $categories,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'カテゴリ一覧の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * ドキュメント一覧を取得
     */
    public function getDocuments(GetDocumentsRequest $request): JsonResponse
    {
        try {
            // 認証チェック（新しいメソッドを使用）
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            $categoryPath = array_filter(explode('/', $request->category_path));

            // カテゴリIDを取得（パスから）
            $parentId = DocumentCategory::getIdFromPath($categoryPath);


            // サブカテゴリを取得
            $subCategories = DocumentCategory::getSubCategories($parentId, $user->userBranches()->id ?? null);

            // ドキュメントを取得
            $documents = DocumentVersion::getDocumentsByCategoryId($parentId, $user->userBranches()->id ?? null);

            // ソート処理
            $sortedDocuments = $documents
                ->filter(function ($doc) {
                    return $doc->file_order !== null;
                })
                ->sortBy('file_order')
                ->map(function ($doc) {
                    return [
                        'sidebar_label' => $doc->sidebar_label,
                        'slug' => $doc->slug,
                        'is_public' => (bool) $doc->is_public,
                        'status' => $doc->status,
                        'last_edited_by' => $doc->last_edited_by,
                        'file_order' => $doc->file_order,
                    ];
                });

            $sortedCategories = $subCategories
                ->filter(function ($cat) {
                    return $cat->position !== null;
                })
                ->sortBy('position')
                ->map(function ($cat) {
                    return [
                        'slug' => $cat->slug,
                        'sidebar_label' => $cat->sidebar_label,
                    ];
                });

            return response()->json([
                'documents' => $sortedDocuments->values(),
                'categories' => $sortedCategories->values(),
            ]);

        } catch (\Exception $e) {
            Log::error('ドキュメント一覧の取得に失敗しました: '.$e);

            return response()->json([
                'error' => 'ドキュメント一覧の取得に失敗しました',
            ], 500);
        }
    }

    /**
     * ドキュメントファイルパスを生成
     *
     * @param  string|null  $category  カテゴリ名
     * @param  string  $slug  ドキュメントのスラッグ
     * @return string 生成されたファイルパス
     */
    private function generateDocumentFilePath(?string $category, string $slug): string
    {
        $targetDir = $category
            ? base_path(DocumentPathConstants::DOCS_BASE_PATH.'/'.$category)
            : base_path(DocumentPathConstants::DOCS_BASE_PATH);

        return $targetDir.'/'.$slug.DocumentPathConstants::DOCUMENT_FILE_EXTENSION;
    }

    /**
     * ドキュメントを作成
     */
    public function createDocument(CreateDocumentRequest $request): JsonResponse
    {
        try {
            // 認証チェック
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch($user->id);

            $categoryPath = array_filter(explode('/', $request->category_path));
            $categoryId = DocumentCategory::getIdFromPath($categoryPath);

            // file_orderの重複処理・自動採番
            $correctedFileOrder = $this->documentService->normalizeFileOrder(
                $request->file_order ? (int) $request->file_order : null,
                $categoryId ?? null
            );

            // ファイルパスの生成
            $filePath = $this->generateDocumentFilePath($request->category, $request->slug);

            $document = Document::create([
                'user_id' => $user->id,
                'user_branch_id' => $userBranchId,
                'category_id' => $categoryId,
                'sidebar_label' => $request->sidebar_label,
                'slug' => $request->slug,
                'is_public' => $request->is_public,
                'status' => DocumentStatus::DRAFT->value,
                'last_edited_by' => $user->email,
                'file_order' => $correctedFileOrder,
                'file_path' => $filePath,
            ]);

            return response()->json([
                'document' => $document,
            ]);

        } catch (\Exception $e) {
            Log::error($e);

            return response()->json([
                'error' => 'ドキュメントの作成に失敗しました',
            ], 500);
        }
    }

    /**
     * スラッグでドキュメントを取得
     */
    public function getDocumentByCategoryPath(GetDocumentByCategoryPathRequest $request): JsonResponse
    {
        try {
            // 認証チェック
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            // パスからslugとcategoryPathを取得
            $pathParts = explode('/', $request->category_path);
            $slug = array_pop($pathParts);

            $categoryId = DocumentCategory::getIdFromPath($pathParts);

            $document = DocumentVersion::where('category_id', $categoryId)
                ->where('slug', $slug)
                ->first();

            if (! $document) {
                return response()->json([
                    'error' => 'ドキュメントが見つかりません',
                ], 404);
            }

            return response()->json($document);

        } catch (\Exception $e) {
            Log::error('ドキュメント取得エラー: '.$e->getMessage());

            return response()->json([
                'error' => 'ドキュメントの取得に失敗しました',
            ], 500);
        }
    }

    /**
     * ドキュメントを更新
     */
    public function updateDocument(UpdateDocumentRequest $request): JsonResponse
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
            // パスからslugとcategoryPathを取得
            $pathParts = explode('/', $request->category_path);
            $slug = array_pop($pathParts);

            $categoryId = DocumentCategory::getIdFromPath($pathParts);

            $existingDocument = DocumentVersion::where('category_id', $categoryId)
                ->where('slug', $slug)
                ->first();

            if (! $existingDocument) {
                return response()->json([
                    'error' => '編集対象のドキュメントが見つかりません',
                ], 404);
            }

            // アクティブブランチを取得
            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch($user->id);

            // file_orderの処理
            $categoryId = $existingDocument->category_id;
            $finalFileOrder = $this->processFileOrder($request->file_order, $categoryId, $existingDocument->file_order, $userBranchId, $existingDocument->id);

            // 既存ドキュメントを論理削除
            $existingDocument->delete();

            // 新しいドキュメントバージョンを作成
            $newDocumentVersion = DocumentVersion::create([
                'user_id' => $user->id,
                'user_branch_id' => $userBranchId,
                'file_path' => $existingDocument->file_path,
                'status' => DocumentStatus::DRAFT->value,
                'content' => $request->content,
                'slug' => $request->slug,
                'sidebar_label' => $request->sidebar_label,
                'file_order' => $finalFileOrder,
                'last_edited_by' => $user->email,
                'is_public' => $request->is_public,
                'category_id' => $categoryId,
            ]);

            // 編集開始バージョンを記録
            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                'original_version_id' => $existingDocument->id,
                'current_version_id' => $newDocumentVersion->id,
            ]);

            DB::commit();

            return response()->json([
                'updated_document' => $newDocumentVersion,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ドキュメント更新エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'ドキュメントの更新に失敗しました',
            ], 500);
        }
    }

    /**
     * ユーザーのアクティブブランチを取得
     */
    private function getUserBranch(int $userId)
    {
        return DB::table('user_branches')
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->where('pr_status', 'none')
            ->first();
    }

    /**
     * file_orderの処理と他のドキュメントの順序調整
     */
    private function processFileOrder($file_order, int $categoryId, int $old_file_order, int $userBranchId, int $excludeId): int
    {
        // file_orderが空の場合は最大値+1を設定
        if (empty($file_order) && $file_order !== 0) {
            $maxOrder = DocumentVersion::where('category_id', $categoryId)
                ->where('status', DocumentStatus::MERGED->value)
                ->max('file_order');

            return ($maxOrder ?? 0) + 1;
        }

        $new_file_order = (int) $file_order;

        // file_orderが変更された場合のみ他のドキュメントを調整
        if ($new_file_order !== $old_file_order) {
            $this->adjustOtherDocumentsOrder($categoryId, $new_file_order, $old_file_order, $userBranchId, $excludeId);
        }

        return $new_file_order;
    }

    /**
     * 他のドキュメントの順序を調整
     */
    private function adjustOtherDocumentsOrder(int $categoryId, int $new_file_order, int $old_file_order, int $userBranchId, int $excludeId): void
    {
        $documentsToShift = DocumentVersion::where('category_id', $categoryId)
            ->where(function ($query) use ($userBranchId) {
                $query->where('status', DocumentStatus::MERGED->value)
                    ->orWhere('user_branch_id', $userBranchId);
            })
            ->where('id', '!=', $excludeId);

        if ($new_file_order < $old_file_order) {
            // 上に移動する場合：新しい位置以上、元の位置未満の範囲のレコードを+1
            $documentsToShift = $documentsToShift
                ->where('file_order', '>=', $new_file_order)
                ->where('file_order', '<', $old_file_order)
                ->orderBy('file_order', 'asc');
        } else {
            // 下に移動する場合：元の位置超過、新しい位置以下の範囲のレコードを-1
            $documentsToShift = $documentsToShift
                ->where('file_order', '>', $old_file_order)
                ->where('file_order', '<=', $new_file_order)
                ->orderBy('file_order', 'asc');
        }

        $documents = $documentsToShift->get();

        foreach ($documents as $doc) {
            $newOrder = $new_file_order < $old_file_order ? $doc->file_order + 1 : $doc->file_order - 1;

            // 新しいバージョンを作成して順序を更新
            DocumentVersion::create([
                'user_id' => $doc->user_id,
                'user_branch_id' => $userBranchId,
                'file_path' => $doc->file_path,
                'status' => DocumentStatus::DRAFT->value,
                'content' => $doc->content,
                'slug' => $doc->slug,
                'sidebar_label' => $doc->sidebar_label,
                'file_order' => $newOrder,
                'last_edited_by' => $doc->last_edited_by,
                'is_deleted' => 0,
                'is_public' => $doc->is_public,
                'category_id' => $doc->category_id,
            ]);

            // 元のバージョンを論理削除
            $doc->update(['is_deleted' => 1]);
        }
    }

    /**
     * 編集開始バージョンを記録
     */
    private function recordEditStartVersion(int $userBranchId, int $originalVersionId, int $currentVersionId): void
    {
        DB::table('edit_start_versions')->updateOrInsert(
            [
                'user_branch_id' => $userBranchId,
                'original_version_id' => $originalVersionId,
            ],
            [
                'target_type' => 'document',
                'current_version_id' => $currentVersionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * ドキュメントを削除
     */
    public function deleteDocument(DeleteDocumentRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // 1. 認証チェック
            $user = $this->getUserFromSession();

            if (! $user) {
                return response()->json([
                    'error' => '認証が必要です',
                ], 401);
            }

            $userBranchId = $this->userBranchService->fetchOrCreateActiveBranch($user->id);

            $categoryPath = array_filter(explode('/', $request->category_path));
            $categoryId = DocumentCategory::getIdFromPath($categoryPath);

            // 3. 削除対象のドキュメントを取得
            $existingDocument = DocumentVersion::where('category_id', $categoryId)
                ->where('slug', $request->slug)
                ->first();

            if (! $existingDocument) {
                return response()->json([
                    'error' => '削除対象のドキュメントが見つかりません',
                ], 404);
            }

            // 4. ユーザーのアクティブブランチ確認
            $userBranch = $this->getUserBranch($user->id);

            if (! $userBranch) {
                // 新しいブランチを作成
                $userBranch = $this->createUserBranch($user->id, $user->email);
            }

            // 5. 既存ドキュメントを論理削除（is_deleted = 1に更新）
            $existingDocument->delete();

            // 6. 削除されたドキュメントのレコードを新規挿入（ブランチ管理用）
            $newDocumentVersion = DocumentVersion::create([
                'user_id' => $user->id,
                'user_branch_id' => $userBranchId,
                'file_path' => $existingDocument->file_path,
                'status' => DocumentStatus::DRAFT->value,
                'content' => $existingDocument->content,
                'slug' => $existingDocument->slug,
                'sidebar_label' => $existingDocument->sidebar_label,
                'file_order' => $existingDocument->file_order,
                'last_edited_by' => $user->email,
                'is_public' => $existingDocument->is_public,
                'category_id' => $existingDocument->category_id,
                'is_deleted' => Flag::TRUE,
            ]);

            EditStartVersion::create([
                'user_branch_id' => $userBranchId,
                'target_type' => EditStartVersionTargetType::DOCUMENT->value,
                'original_version_id' => $existingDocument->id,
                'current_version_id' => $newDocumentVersion->id,
            ]);

            DB::commit();

            // 7. 成功レスポンス
            return response()->json();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ドキュメント削除エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'ドキュメントの削除に失敗しました',
            ], 500);
        }
    }

    /**
     * ユーザーブランチを作成
     */
    private function createUserBranch(int $userId, string $email)
    {
        // ブランチスナップショットの初期化処理
        GitController::initBranchSnapshot($userId, $email);

        // 作成されたブランチを取得
        $userBranch = DB::table('user_branches')
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->orderBy('id', 'desc')
            ->first();

        if (! $userBranch) {
            throw new \Exception('ブランチの作成に失敗しました');
        }

        return $userBranch;
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
            Log::error('カテゴリコンテンツ取得エラー: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'カテゴリコンテンツの取得に失敗しました',
            ], 500);
        }
    }
}
