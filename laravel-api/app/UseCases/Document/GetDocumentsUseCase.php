<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\Document\GetDocumentsDto;
use App\Models\PullRequest;
use App\Services\CategoryService;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Log;

class GetDocumentsUseCase
{
    public function __construct(
        private DocumentService $documentService,
        private CategoryService $CategoryService
    ) {}

    /**
     * ドキュメント一覧を取得
     *
     * @param  GetDocumentsDto  $dto  リクエストデータ
     * @param  object  $user  認証済みユーザー
     * @return array{success: bool, documents?: array, categories?: array, error?: string}
     */
    public function execute(GetDocumentsDto $dto, object $user): array
    {
        try {
            $categoryPath = array_filter(explode('/', $dto->category_path ?? ''));

            // カテゴリIDを取得（パスから）
            $parentId = $this->CategoryService->getIdFromPath(implode('/', $categoryPath));

            $userBranchId = $user->userBranches()->active()->orderBy('id', 'desc')->first()->id ?? null;

            Log::info('userBranchId: '.$userBranchId);

            // edit_pull_request_idが存在する場合、プルリクエストからuser_branch_idを取得
            if (! empty($dto->edit_pull_request_id)) {
                $pullRequest = PullRequest::find($dto->edit_pull_request_id);
                $userBranchId = $pullRequest?->user_branch_id ?? null;
            }

            // サブカテゴリを取得
            $subCategories = $this->CategoryService->getSubCategories(
                $parentId,
                $userBranchId,
                $dto->edit_pull_request_id
            );

            // ドキュメントを取得
            $documents = $this->documentService->fetchDocumentsByCategoryId(
                $parentId,
                $userBranchId,
                $dto->edit_pull_request_id
            );

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

            return [
                'success' => true,
                'documents' => $sortedDocuments->values(),
                'categories' => $sortedCategories->values(),
            ];

        } catch (\Exception $e) {
            Log::error('ドキュメント一覧の取得に失敗しました: '.$e);

            return [
                'success' => false,
                'error' => 'ドキュメント一覧の取得に失敗しました',
            ];
        }
    }
}
