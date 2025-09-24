<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\DocumentVersion\DetailDto;
use App\Exceptions\DocumentNotFoundException;
use App\Models\DocumentVersion;
use App\Repositories\Interfaces\DocumentVersionRepositoryInterface;
use App\Services\DocumentCategoryService;
use App\Models\User;
use Http\Discovery\Exception\NotFoundException;
use Illuminate\Support\Facades\Log;

class DetailUseCase
{
    public function __construct(
        private DocumentCategoryService $documentCategoryService,
        private DocumentVersionRepositoryInterface $documentVersionRepository
    ) {}

    /**
     * IDでドキュメントを取得
     *
     * @param  DetailDto  $dto  リクエストデータ
     */
    public function execute(DetailDto $dto, User $user): array
    {
        try {
            $organizationId = $user->organizationMember->organization_id;

            // 指定されたIDとorganization_idでドキュメントバージョンを取得（カテゴリの親階層も一緒に読み込み）
            $document = DocumentVersion::with(['category' => function ($query) {
                $query->with('parent.parent.parent.parent.parent.parent.parent'); // 7階層まで親カテゴリを読み込み
            }])
                ->where('id', $dto->id)
                ->where('organization_id', $organizationId)
                ->first();

            if (! $document) {
                throw new NotFoundException();
            }

            // パンクズリストを生成
            $breadcrumbs = [];
            if ($document->category) {
                $categoryBreadcrumbs = $document->category->getBreadcrumbs();
                $breadcrumbs = array_merge($categoryBreadcrumbs, [
                    [
                        'id' => $document->id,
                        'title' => $document->title
                    ]
                ]);
            }

            return [
                'id' => $document->id,
                'title' => $document->title,
                'description' => $document->description,
                'category' => [
                    'id' => $document->category?->id,
                    'title' => $document->category?->title
                ],
                'breadcrumbs' => $breadcrumbs
            ];
        } catch (\Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}
