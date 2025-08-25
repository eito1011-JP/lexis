<?php

declare(strict_types=1);

namespace App\Dto\UseCase\Document;

use App\Dto\UseCase\UseCaseDto;

final class GetDocumentDetailDto extends UseCaseDto
{
    public function __construct(
        public readonly ?string $category_path,
        public readonly string $slug,
    ) {}
}
