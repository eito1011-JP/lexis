<?php

declare(strict_types=1);

namespace App\Dto\UseCase\PullRequest;

use App\Dto\UseCase\UseCaseDto;

/**
 * プルリクエストタイトル更新DTO
 */
class UpdatePullRequestTitleDto extends UseCaseDto
{
    /**
     * @param  int  $pullRequestId  プルリクエストID
     * @param  string  $title  新しいタイトル
     * @param  string  $description  新しい説明
     */
    public function __construct(
        public readonly int $pullRequestId,
        public readonly ?string $title,
        public readonly ?string $description,
    ) {}
}
