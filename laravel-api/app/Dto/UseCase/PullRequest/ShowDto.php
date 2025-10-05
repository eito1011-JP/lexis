<?php

declare(strict_types=1);

namespace App\Dto\UseCase\PullRequest;

use App\Dto\UseCase\UseCaseDto;

/**
 * プルリクエスト詳細取得DTO
 */
class ShowDto extends UseCaseDto
{
    /**
     * @param  int  $pullRequestId  プルリクエストID
     */
    public function __construct(
        public readonly int $pullRequestId,
    ) {}
}
