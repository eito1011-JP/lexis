<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\DocumentVersion\DetailDto;
use App\Models\User;
use App\Repositories\Interfaces\DocumentVersionRepositoryInterface;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\Log;

class DetailUseCase
{
    public function __construct(
        private DocumentCategoryService $documentCategoryService,
        private DocumentVersionRepositoryInterface $documentVersionRepository,
        private DocumentService $documentService
    ) {}

    /**
     * IDでドキュメントを取得
     *
     * @param  DetailDto  $dto  リクエストデータ
     */
    public function execute(DetailDto $dto, User $user): array
    {
        try {
            // 作業コンテキストに応じて適切なドキュメントを取得
            $document = $this->documentService->getDocumentByWorkContext(
                $dto->entityId,
                $user,
                $dto->pullRequestEditSessionToken ?? null
            );

            if (! $document) {
                throw new NotFoundException;
            }

            // パンクズリストを生成
            $breadcrumbs = [];
            $categoryBreadcrumbs = $document->category->getBreadcrumbs();
            $breadcrumbs = array_merge($categoryBreadcrumbs, [
                [
                    'id' => $document->id,
                    'title' => $document->title,
                ],
            ]);

            return [
                'id' => $document->id,
                'title' => $document->title,
                'description' => $document->description,
                'category' => [
                    'id' => $document->category->id,
                    'title' => $document->category->title,
                ],
                'breadcrumbs' => $breadcrumbs,
            ];
        } catch (\Exception $e) {
            Log::error($e);

            throw $e;
        }
    }
}
