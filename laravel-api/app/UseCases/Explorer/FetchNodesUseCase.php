<?php

namespace App\UseCases\Explorer;

use App\Dto\UseCase\Explorer\FetchNodesDto;
use App\Models\CategoryEntity;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\DocumentService;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\Log;

class FetchNodesUseCase
{
    public function __construct(
        private CategoryService $CategoryService,
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
        try {
            $categoryEntity = CategoryEntity::find($dto->categoryEntityId);

            if (! $categoryEntity) {
                throw new NotFoundException('カテゴリエンティティが見つかりません。');
            }

            // categoryEntityに従属しているcategoryEntityでforeach
            // 同じentity_idの重複を除外
            $categoryEntityIds = $categoryEntity->categoryVersionChildren
                ->pluck('entity_id')
                ->unique();
            
            $categories = collect();
            foreach ($categoryEntityIds as $entityId) {
                $categories->push($this->CategoryService->getCategoryByWorkContext(
                    $entityId,
                    $user,
                    $dto->pullRequestEditSessionToken
                ));
            }

            // categoryEntityに従属しているdocumentEntityでforeach
            // 同じentity_idの重複を除外
            $documentEntityIds = $categoryEntity->documentVersionChildren
                ->pluck('entity_id')
                ->unique();

            $documents = collect();
            foreach ($documentEntityIds as $entityId) {
                $documents->push($this->documentService->getDocumentByWorkContext(
                    $entityId,
                    $user,
                    $dto->pullRequestEditSessionToken
                ));
            }

            return [
                'categories' => $categories->filter()->values()->toArray(),
                'documents' => $documents->filter()->values()->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}
