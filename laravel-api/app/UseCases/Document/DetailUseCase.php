<?php

namespace App\UseCases\Document;

use App\Dto\UseCase\DocumentVersion\DetailDto;
use App\Exceptions\DocumentNotFoundException;
use App\Models\DocumentVersion;
use App\Repositories\Interfaces\DocumentVersionRepositoryInterface;
use App\Services\DocumentCategoryService;
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
    public function execute(DetailDto $dto): array
    {
        try {
            // 指定されたIDとorganization_idでドキュメントバージョンを取得
            $document = DocumentVersion::where('id', $dto->id)
                ->where('organization_id', $dto->organizationId)
                ->first();

            if (! $document) {
                throw new DocumentNotFoundException('指定されたドキュメントは見つかりませんでした');
            }

            return [
                'id' => $document->id,
                'title' => $document->title,
                'description' => $document->description,
            ];

        } catch (DocumentNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GetDocumentDetailUseCase: エラー', [
                'error' => $e->getMessage(),
                'id' => $dto->id,
                'organization_id' => $dto->organizationId,
            ]);

            throw $e;
        }
    }
}
