<?php

namespace App\Services;

use Github\Client;
use Illuminate\Support\Facades\Log;

class GitService
{
    private Client $client;

    private string $owner;

    private string $repo;

    private const DEFAULT_BASE_BRANCH = 'main';

    public function __construct()
    {
        $this->client = new Client;
        $this->client->authenticate(config('services.github.token'), null, Client::AUTH_ACCESS_TOKEN);
        $this->owner = config('services.github.owner');
        $this->repo = config('services.github.repo');
    }

    /**
     * プルリクエストを作成
     */
    public function createPullRequest(string $branchName, string $title, string $body = '', array $reviewers = []): array
    {
        try {
            $response = $this->client->pullRequests()->create($this->owner, $this->repo, [
                'title' => $title,
                'body' => $body,
                'head' => $branchName,
                'base' => self::DEFAULT_BASE_BRANCH,
            ]);

            $prNumber = $response['number'];
            $prUrl = $response['html_url'];

            // レビュアーが指定されている場合、追加する
            if (! empty($reviewers)) {
                $this->addReviewers($prNumber, $reviewers);
            }

            return [
                'success' => true,
                'pr_url' => $prUrl,
                'pr_number' => $prNumber,
            ];
        } catch (\Exception $e) {
            Log::error('GitHub API プルリクエスト作成エラー: '.$e->getMessage());

            throw new \Exception('プルリクエストの作成に失敗しました: '.$e->getMessage());
        }
    }

    /**
     * プルリクエストにレビュアーを追加
     */
    public function addReviewers(int $prNumber, array $reviewers): void
    {
        try {
            $this->client->pullRequests()->reviewRequests()->create($this->owner, $this->repo, $prNumber, [
                'reviewers' => $reviewers,
            ]);
        } catch (\Exception $e) {
            Log::error('GitHub API レビュアー追加エラー: '.$e->getMessage());
            // レビュアー追加に失敗してもPR作成は成功とする
        }
    }

    /**
     * ファイルを作成または更新
     */
    public function createOrUpdateFile(string $path, string $content, string $branchName, string $message): array
    {
        try {
            $response = $this->client->repositories()->contents()->create($this->owner, $this->repo, $path, $content, $message, $branchName);

            return [
                'success' => true,
                'sha' => $response['content']['sha'],
            ];
        } catch (\Exception $e) {
            Log::error('GitHub API ファイル作成エラー: '.$e->getMessage());

            throw new \Exception('ファイルの作成に失敗しました: '.$e->getMessage());
        }
    }

    /**
     * 最新のコミットハッシュを取得
     */
    public function getLatestCommit(): string
    {
        try {
            $response = $this->client->gitData()->references()->show($this->owner, $this->repo, 'heads/'.self::DEFAULT_BASE_BRANCH);

            return $response['object']['sha'];
        } catch (\Exception $e) {
            Log::error('GitHub API 最新コミット取得エラー: '.$e->getMessage());

            throw new \Exception('最新のコミット取得に失敗しました: '.$e->getMessage());
        }
    }
}
