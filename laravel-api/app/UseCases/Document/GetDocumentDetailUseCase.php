<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\Document\GetDocumentDetailDto;
use App\Repositories\Interfaces\DocumentVersionRepositoryInterface;
use App\Services\DocumentCategoryService;
use Illuminate\Support\Facades\Log;

class GetDocumentDetailUseCase
{
    public function __construct(
        private DocumentCategoryService $documentCategoryService,
        private DocumentVersionRepositoryInterface $documentVersionRepository
    ) {}

    /**
     * スラッグでドキュメントを取得
     *
     * @param  GetDocumentDetailDto  $dto  リクエストデータ
     * @return array{success: bool, document?: object, error?: string}
     */
    public function execute(GetDocumentDetailDto $dto): array
    {
        try {
            // パスから所属しているカテゴリのcategoryIdを取得
            $categoryId = $this->documentCategoryService->getIdFromPath($dto->category_path ?? '');

            $document = $this->documentVersionRepository->findByCategoryAndSlug($categoryId, $dto->slug);

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
                'category_path' => $dto->category_path,
                'slug' => $dto->slug,
            ]);

            return [
                'success' => false,
                'error' => 'ドキュメントの取得に失敗しました',
            ];
        }
    }
}
