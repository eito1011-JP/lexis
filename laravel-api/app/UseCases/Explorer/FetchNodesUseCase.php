<?php

namespace App\UseCases\Explorer;

use App\Dto\UseCase\Explorer\FetchNodesDto;
use App\Models\User;
use App\Models\DocumentCategoryEntity;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\Log;

class FetchNodesUseCase
{
    public function __construct(
        private DocumentCategoryService $documentCategoryService,
        private DocumentService $documentService
    ) {}

    /**
     * 指定されたカテゴリに従属するカテゴリとドキュメントを取得
     *
     * @param  FetchNodesDto  $dto  リクエストデータのDTO
     * @param  User  $user  認証済みユーザー
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function execute(FetchNodesDto $dto, User $user): array
    {
        $categoryEntity = DocumentCategoryEntity::find($dto->categoryEntityId);

        if (!$categoryEntity) {
            throw new NotFoundException('カテゴリエンティティが見つかりません。');
        }

        Log::info('categoryEntity'.json_encode($categoryEntity));
        Log::info('categoryEntityId'.json_encode($dto->categoryEntityId));
        Log::info('documentcategorychildren'.json_encode($categoryEntity->documentCategoryChildren));
        Log::info('documentversionchildren'.json_encode($categoryEntity->documentVersionChildren));

        // categoryEntityに従属しているcategoryEntityでforeach
        $categories = collect();
        foreach ($categoryEntity->documentCategoryChildren as $childCategory) {
            $categories->push($this->documentCategoryService->getCategoryByWorkContext(
                $childCategory->id,
                $user,
                $dto->pullRequestEditSessionToken
            ));
        }

        Log::info('categories'.json_encode($categories));
        // categoryEntityに従属しているdocumentEntityでforeach
        $documents = collect();
        foreach ($categoryEntity->documentVersionChildren as $childCategory) {
            $documents->push($this->documentService->getDocumentByWorkContext(
                $childCategory->id,
                $user,
                $dto->pullRequestEditSessionToken
            ));
        }
        Log::info('documents'.json_encode($documents));

        return [
            'categories' => $categories,
            'documents' => $documents,
        ];
    }
}
