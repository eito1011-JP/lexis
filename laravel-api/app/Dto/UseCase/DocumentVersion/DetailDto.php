<?php

namespace App\Dto\UseCase\DocumentVersion;

use App\Dto\UseCase\UseCaseDto;

class DetailDto extends UseCaseDto
{
    public function __construct(
        public readonly int $id
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            id: $data['id'],
        );
    }
}
