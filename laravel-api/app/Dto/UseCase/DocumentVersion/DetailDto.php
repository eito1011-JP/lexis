<?php

namespace App\Dto\UseCase\DocumentVersion;

use App\Dto\UseCase\UseCaseDto;

class DetailDto extends UseCaseDto
{
    public function __construct(
        public readonly int $entityId,
        public readonly ?string $pullRequestEditSessionToken = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            entityId: $data['entity_id'],
            pullRequestEditSessionToken: $data['pull_request_edit_session_token'] ?? null
        );
    }
}
