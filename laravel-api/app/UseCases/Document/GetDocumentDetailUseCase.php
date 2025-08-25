<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\Document\GetDocumentDetailDto;
use App\Exceptions\DocumentNotFoundException;
use App\Repositories\Interfaces\DocumentVersionRepositoryInterface;
use App\Services\DocumentCategoryService;
use App\Models\DocumentVersion;
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
     * @return DocumentVersion
     */
    public function execute(GetDocumentDetailDto $dto): DocumentVersion
    {
        try {
            // パスから所属しているカテゴリのcategoryIdを取得
            $categoryId = $this->documentCategoryService->getIdFromPath($dto->category_path);

            $document = $this->documentVersionRepository->findByCategoryAndSlug($categoryId, $dto->slug);

            if (! $document) {
                throw new DocumentNotFoundException('ドキュメントが見つかりません');
            }

            return $document;

        } catch (DocumentNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GetDocumentDetailUseCase: エラー', [
                'error' => $e->getMessage(),
                'category_path' => $dto->category_path,
                'slug' => $dto->slug,
            ]);

            throw $e;
        }
    }
}
