<?php

namespace App\Jobs;

use App\Models\PullRequest;
use App\Services\GitService;
use App\Services\PullRequestMergeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PullRequestMergeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected int $pullRequestId;

    protected int $userId;

    protected int $maxRetries;

    protected int $currentRetry;

    /**
     * Create a new job instance.
     */
    public function __construct(int $pullRequestId, int $userId, int $currentRetry = 0, int $maxRetries = 60)
    {
        $this->pullRequestId = $pullRequestId;
        $this->userId = $userId;
        $this->currentRetry = $currentRetry;
        $this->maxRetries = $maxRetries; // 最大60回（60分間）リトライ
    }

    /**
     * Execute the job.
     */
    public function handle(PullRequestMergeService $pullRequestMergeService, GitService $gitService): void
    {
        try {
            // プルリクエストを取得
            $pullRequest = PullRequest::find($this->pullRequestId);

            if (! $pullRequest) {
                return;
            }

            // プルリクエストがopenedでない場合は処理を終了
            if ($pullRequest->status !== \App\Enums\PullRequestStatus::OPENED->value) {
                return;
            }

            // mergeable stateをチェック
            $prInfo = $gitService->getPullRequestInfo($pullRequest->pr_number);

            // mergeableがfalseの場合
            if ($prInfo['mergeable'] === false) {
                // 最大リトライ回数に達していない場合は1分後に再実行
                if ($this->currentRetry < $this->maxRetries) {

                    // 1分後に再実行
                    PullRequestMergeJob::dispatch(
                        $this->pullRequestId,
                        $this->userId,
                        $this->currentRetry + 1,
                        $this->maxRetries
                    )->delay(now()->addMinutes(1));

                    return;
                }
            }

            // mergeableがtrueの場合はマージ処理を実行
            $pullRequestMergeService->mergePullRequest($this->pullRequestId, $this->userId);

        } catch (\Exception $e) {
            Log::error("PullRequestMergeJob failed for PR {$this->pullRequestId}: {$e->getMessage()}", [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'pull_request_id' => $this->pullRequestId,
                'user_id' => $this->userId,
                'current_retry' => $this->currentRetry,
            ]);

            // ジョブの実行を失敗させる
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("PullRequestMergeJob permanently failed for PR {$this->pullRequestId}: {$exception->getMessage()}", [
            'exception' => $exception,
            'pull_request_id' => $this->pullRequestId,
            'user_id' => $this->userId,
            'current_retry' => $this->currentRetry,
        ]);
    }
}
