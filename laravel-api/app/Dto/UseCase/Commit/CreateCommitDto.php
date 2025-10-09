<?php

declare(strict_types=1);

namespace App\Dto\UseCase\Commit;

use App\Dto\UseCase\UseCaseDto;

/**
 * コミット作成DTO
 */
class CreateCommitDto extends UseCaseDto
{
    /**
     * @param  int  $pullRequestId  プルリクエストID
     * @param  string  $message  コミットメッセージ
     */
    public function __construct(
        public readonly int $pullRequestId,
        public readonly string $message,
    ) {}
}
