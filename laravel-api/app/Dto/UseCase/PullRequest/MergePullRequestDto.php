<?php

namespace App\Dto\UseCase\PullRequest;

use App\Dto\UseCase\UseCaseDto;

class MergePullRequestDto extends UseCaseDto
{
    public int $pullRequestId;

    public int $userId;

    public function __construct(int $pullRequestId, int $userId)
    {
        $this->pullRequestId = $pullRequestId;
        $this->userId = $userId;
    }
}
