<?php

namespace App\UseCases\Explorer;

use App\Dto\UseCase\Explorer\FetchNodesDto;
use App\Models\User;
use App\Models\DocumentCategoryEntity;
use App\Services\DocumentCategoryService;
use App\Services\DocumentService;
use Http\Discovery\Exception\NotFoundException;

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
        $activeUserBranch = $user->userBranches()->active()->first();

        $categoryEntity = DocumentCategoryEntity::find($dto->categoryEntityId);

        if (!$categoryEntity) {
            throw new NotFoundException('カテゴリエンティティが見つかりません。');
        }

        // categoryEntityに従属しているcategoryEntityでforeach
        $categories = $this->documentCategoryService->getCategoryByWorkContext(
            $dto->categoryEntityId,
            $user,
            $dto->pullRequestEditSessionToken
        );

        // categoryEntityに従属しているdocumentEntityでforeach
        $documents = $this->documentService->getDocumentByWorkContext(
            $dto->categoryEntityId,
            $user,
            $dto->pullRequestEditSessionToken
        );

        return [
            'categories' => $categories,
            'documents' => $documents,
        ];
    }
}
