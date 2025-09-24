<?php

declare(strict_types=1);

namespace App\Dto\UseCase\Document;

use App\Dto\UseCase\UseCaseDto;

final class UpdateDocumentDto extends UseCaseDto
{
    public function __construct(
        public readonly int $current_document_id,
        public readonly string $title,
        public readonly string $description,
        public readonly ?int $edit_pull_request_id = null,
        public readonly ?string $pull_request_edit_token = null,
    ) {}
}
