<?php

namespace App\UseCases\Document;

use App\Models\DocumentVersion;
use App\Services\DocumentCategoryService;
use Illuminate\Support\Facades\Log;

class GetDocumentDetailUseCase
{
    public function __construct(
        private DocumentCategoryService $documentCategoryService
    ) {}

    /**
     * スラッグでドキュメントを取得
     *
     * @param string $categoryPath カテゴリパス（例: 'tutorial/basics'）
     * @param string $slug ドキュメントのスラッグ
     * @return array{success: bool, document?: object, error?: string}
     */
    public function execute(string $categoryPath, string $slug): array
    {
        try {
            // パスから所属しているカテゴリのcategoryIdを取得
            $categoryId = $this->documentCategoryService->getIdFromPath($categoryPath);

            $document = DocumentVersion::where(function ($query) use ($categoryId, $slug) {
                $query->where('category_id', $categoryId)
                    ->where('slug', $slug);
            })
                ->first();

            if (! $document) {
                return [
                    'success' => false,
                    'error' => 'ドキュメントが見つかりません',
                ];
            }

            return [
                'success' => true,
                'document' => $document,
            ];

        } catch (\Exception $e) {
            Log::error('GetDocumentDetailUseCase: エラー', [
                'error' => $e->getMessage(),
                'category_path' => $categoryPath,
                'slug' => $slug,
            ]);

            return [
                'success' => false,
                'error' => 'ドキュメントの取得に失敗しました',
            ];
        }
    }
}
