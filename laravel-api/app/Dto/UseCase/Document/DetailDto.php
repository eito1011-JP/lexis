<?php

namespace App\Dto\UseCase\DocumentVersion;

use App\Dto\UseCase\UseCaseDto;

class DetailDto extends UseCaseDto
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $organizationId
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            userId: $data['user_id'],
            organizationId: $data['organization_id']
        );
    }
}
