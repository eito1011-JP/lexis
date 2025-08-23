<?php

declare(strict_types=1);

namespace App\Dto\UseCase\Document;

use App\Dto\UseCase\UseCaseDto;

final class UpdateDocumentDto extends UseCaseDto
{
    public function __construct(
        public readonly ?string $category_path,
        public readonly int $current_document_id,
        public readonly string $sidebar_label,
        public readonly string $content,
        public readonly bool $is_public,
        public readonly string $slug,
        public readonly ?int $file_order,
        public readonly ?int $edit_pull_request_id,
        public readonly ?string $pull_request_edit_token,
    ) {}
}
