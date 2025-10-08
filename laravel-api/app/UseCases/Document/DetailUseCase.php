<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\DocumentVersion\DetailDto;
use App\Models\User;
use App\Repositories\Interfaces\DocumentVersionRepositoryInterface;
use App\Services\CategoryService;
use App\Services\DocumentService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\Log;

class DetailUseCase
{
    public function __construct(
        private CategoryService $CategoryService,
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
            );

            if (! $document) {
                throw new NotFoundException;
            }

            // パンクズリストを生成
            $breadcrumbs = [];
            if ($document->category) {
                $categoryBreadcrumbs = $document->category->getBreadcrumbs();
                $breadcrumbs = array_merge($categoryBreadcrumbs, [
                    [
                        'id' => $document->id,
                        'title' => $document->title,
                    ],
                ]);
            } else {
                // カテゴリがnull（削除済み）の場合は、ドキュメントのみ
                $breadcrumbs = [
                    [
                        'id' => $document->id,
                        'title' => $document->title,
                    ],
                ];
            }

            return [
                'id' => $document->id,
                'title' => $document->title,
                'description' => $document->description,
                'category' => $document->category ? [
                    'id' => $document->category->id,
                    'title' => $document->category->title,
                ] : null,
                'breadcrumbs' => $breadcrumbs,
            ];
        } catch (\Exception $e) {
            Log::error($e);

            throw $e;
        }
    }
}
