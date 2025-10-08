<?php

declare(strict_types=1);

namespace App\Dto\UseCase\Document;

use App\Dto\UseCase\UseCaseDto;

final class UpdateDocumentDto extends UseCaseDto
{
    public function __construct(
        public readonly int $document_entity_id,
        public readonly string $title,
        public readonly string $description,
    ) {}
}
