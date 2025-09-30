<?php

declare(strict_types=1);

namespace App\Dto\UseCase\Document;

use App\Dto\UseCase\UseCaseDto;

final class DestroyDocumentDto extends UseCaseDto
{
    public function __construct(
        public readonly int $document_entity_id,
        public readonly ?int $edit_pull_request_id = null,
        public readonly ?string $pull_request_edit_token = null,
    ) {}
}
