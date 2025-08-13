<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitService
{
    protected string $githubToken;

    protected string $githubOwner;

    protected string $githubRepo;

    protected string $baseBranch;

    public function __construct()
    {
        $this->githubToken = config('services.github.token');
        $this->githubOwner = config('services.github.owner');
        $this->githubRepo = config('services.github.repo');
        $this->baseBranch = config('services.github.base_branch', 'main');
    }

    /**
     * リモートブランチを作成
     */
    public function createRemoteBranch(string $branchName, string $sha): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/git/refs";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->post($url, [
            'ref' => "refs/heads/{$branchName}",
            'sha' => $sha,
        ]);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Create Branch: '.$response->body());

            throw new \Exception('ブランチの作成に失敗しました');
        }

        return $response->json();
    }

    /**
     * ツリーを作成
     */
    public function createTree(string $baseTreeSha, array $treeItems): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/git/trees";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->post($url, [
            'base_tree' => $baseTreeSha,
            'tree' => $treeItems,
        ]);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Create Tree: '.$response->body());

            throw new \Exception('ツリーの作成に失敗しました');
        }

        return $response->json();
    }

    /**
     * コミットを作成
     */
    public function createCommit(string $message, string $treeSha, array $parents, bool $autoMerge = false): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/git/commits";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->post($url, [
            'message' => $message,
            'tree' => $treeSha,
            'parents' => $parents,
        ]);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Create Commit: '.$response->body());

            throw new \Exception('コミットの作成に失敗しました');
        }

        return $response->json();
    }

    /**
     * ブランチの参照を更新
     */
    public function updateBranchReference(string $branchName, string $sha, bool $force = false): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/git/refs/heads/{$branchName}";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->patch($url, [
            'sha' => $sha,
            'force' => $force,
        ]);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Update Branch Reference: '.$response->body());

            throw new \Exception('ブランチ参照の更新に失敗しました');
        }

        return $response->json();
    }

    /**
     * プルリクエストを作成
     */
    public function createPullRequest(string $branchName, string $title, string $body): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/pulls";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->post($url, [
            'title' => $title,
            'body' => $body,
            'head' => $branchName,
            'base' => $this->baseBranch,
        ]);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Create Pull Request: '.$response->body());

            throw new \Exception('プルリクエストの作成に失敗しました');
        }

        $responseData = $response->json();

        return [
            'pr_url' => $responseData['html_url'],
            'pr_number' => $responseData['number'],
        ];
    }

    /**
     * プルリクエストにレビュアーを追加
     */
    public function addReviewersToPullRequest(int $prNumber, array $reviewers): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/pulls/{$prNumber}/requested_reviewers";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->post($url, [
            'reviewers' => $reviewers,
            'team_reviewers' => [],
        ]);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Add Reviewers: '.$response->body());

            throw new \Exception('レビュアーの追加に失敗しました');
        }

        return $response->json();
    }

    /**
     * プルリクエストをマージ
     */
    public function mergePullRequest(int $prNumber, string $mergeMethod = 'merge', bool $updateBranch = true): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/pulls/{$prNumber}/merge";

        $requestData = [
            'merge_method' => $mergeMethod,
        ];

        // GitHub Enterprise Server以外の場合はupdate_branchパラメータを追加
        if ($updateBranch) {
            $requestData['update_branch'] = true;
        }

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->put($url, $requestData);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Merge Pull Request: '.$response->body());

            throw new \Exception('プルリクエストのマージに失敗しました');
        }

        return $response->json();
    }

    /**
     * プルリクエストの情報を取得（コンフリクト検知用）
     */
    public function getPullRequestInfo(int $prNumber): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/pulls/{$prNumber}";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->get($url);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Get Pull Request Info: '.$response->body());

            throw new \Exception('プルリクエスト情報の取得に失敗しました');
        }

        $data = $response->json();

        return [
            'mergeable' => $data['mergeable'] ?? null,
            'mergeable_state' => $data['mergeable_state'] ?? 'unknown',
            'rebaseable' => $data['rebaseable'] ?? null,
        ];
    }

    /**
     * プルリクエストをクローズ
     */
    public function closePullRequest(int $prNumber): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/pulls/{$prNumber}";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->patch($url, [
            'state' => 'closed',
        ]);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Close Pull Request: '.$response->body());

            throw new \Exception('プルリクエストのクローズに失敗しました');
        }

        return $response->json();
    }

    /**
     * ブランチの参照情報を取得
     */
    public function getBranchReference(string $branchName): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/git/refs/heads/{$branchName}";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->get($url);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Get Branch Reference: '.$response->body());

            throw new \Exception('ブランチ参照の取得に失敗しました');
        }

        return $response->json();
    }

    /**
     * マージコミットを作成
     */
    public function createMergeCommit(string $message, string $baseSha, string $headSha): array
    {
        // まず、両方のブランチのコミット情報を取得
        $baseCommit = $this->getCommit($baseSha);
        $headCommit = $this->getCommit($headSha);

        // マージされたツリーSHAを使用（通常はheadのツリーを使用）
        $mergedTreeSha = $headCommit['tree']['sha'];

        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/git/commits";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->post($url, [
            'message' => $message,
            'tree' => $mergedTreeSha,
            'parents' => [$baseSha, $headSha],
        ]);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Create Merge Commit: '.$response->body());

            throw new \Exception('マージコミットの作成に失敗しました');
        }

        return $response->json();
    }

    /**
     * コミット情報を取得
     */
    public function getCommit(string $sha): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/git/commits/{$sha}";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->get($url);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Get Commit: '.$response->body());

            throw new \Exception('コミット情報の取得に失敗しました');
        }

        return $response->json();
    }

    /**
     * プルリクエストブランチを最新状態に更新
     */
    public function updatePullRequestBranch(int $prNumber): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/pulls/{$prNumber}/update-branch";

        // 空のボディではなく、Content-Typeヘッダーを明示的に設定
        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
            'Content-Type' => 'application/json',
        ])->put($url, null);

        return $response->json();
    }

    /**
     * Compare two refs (base...head)
     */
    public function compareCommits(string $base, string $head): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/compare/{$base}...{$head}";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->get($url);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Compare Commits: '.$response->body());

            throw new \Exception('コミット比較の取得に失敗しました');
        }

        return $response->json();
    }

    /**
     * Get tree recursively by commit SHA
     */
    public function getTreeRecursiveByCommit(string $commitSha): array
    {
        $commit = $this->getCommit($commitSha);
        $treeSha = $commit['tree']['sha'] ?? null;

        if (! $treeSha) {
            throw new \Exception('ツリーSHAの取得に失敗しました');
        }

        return $this->getTreeRecursiveByTreeSha($treeSha);
    }

    /**
     * Get tree recursively by branch name
     */
    public function getTreeRecursiveByBranch(string $branchName): array
    {
        $ref = $this->getBranchReference($branchName);
        $commitSha = $ref['object']['sha'] ?? null;

        if (! $commitSha) {
            throw new \Exception('ブランチ参照からコミットSHAの取得に失敗しました');
        }

        return $this->getTreeRecursiveByCommit($commitSha);
    }

    /**
     * Get tree recursively by tree SHA
     */
    public function getTreeRecursiveByTreeSha(string $treeSha): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/git/trees/{$treeSha}";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->get($url, [
            'recursive' => 1,
        ]);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Get Tree: '.$response->body());

            throw new \Exception('ツリーの取得に失敗しました');
        }

        return $response->json();
    }

    /**
     * Get non-recursive tree by tree SHA (list direct children only)
     */
    public function getTreeByTreeSha(string $treeSha): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/git/trees/{$treeSha}";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->get($url);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Get Tree (non-recursive): '.$response->body());

            throw new \Exception('ツリー（非再帰）の取得に失敗しました');
        }

        return $response->json();
    }

    /**
     * Resolve a directory path to its tree SHA starting from a commit SHA
     */
    protected function resolveTreeShaByPathFromCommit(string $commitSha, string $dirPath): ?string
    {
        $commit = $this->getCommit($commitSha);
        $currentTreeSha = $commit['tree']['sha'] ?? null;
        if (! $currentTreeSha) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($dirPath, '/'))));
        foreach ($segments as $segment) {
            $tree = $this->getTreeByTreeSha($currentTreeSha);
            $next = null;
            foreach ($tree['tree'] ?? [] as $node) {
                if (($node['type'] ?? '') === 'tree' && ($node['path'] ?? '') === $segment) {
                    $next = $node['sha'] ?? null;

                    break;
                }
            }
            if (! $next) {
                return null;
            }
            $currentTreeSha = $next;
        }

        return $currentTreeSha;
    }

    /**
     * Get subtree (e.g., docs/) recursively by commit SHA
     */
    public function getSubtreeRecursiveByCommit(string $commitSha, string $dirPath): array
    {
        $subtreeSha = $this->resolveTreeShaByPathFromCommit($commitSha, $dirPath);
        if (! $subtreeSha) {
            return ['tree' => []];
        }

        return $this->getTreeRecursiveByTreeSha($subtreeSha);
    }

    /**
     * Get subtree (e.g., docs/) recursively by branch
     */
    public function getSubtreeRecursiveByBranch(string $branchName, string $dirPath): array
    {
        $ref = $this->getBranchReference($branchName);
        $commitSha = $ref['object']['sha'] ?? null;
        if (! $commitSha) {
            return ['tree' => []];
        }

        return $this->getSubtreeRecursiveByCommit($commitSha, $dirPath);
    }

    /**
     * Get single blob by SHA
     */
    public function getBlob(string $blobSha): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/git/blobs/{$blobSha}";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->get($url);

        if (! $response->successful()) {
            Log::error('GitHub API Error - Get Blob: '.$response->body());

            throw new \Exception('ブロブの取得に失敗しました');
        }

        return $response->json();
    }

    /**
     * Get multiple blobs concurrently
     */
    public function getBlobs(array $blobShas): array
    {
        $t0 = microtime(true);
        $blobShas = array_values(array_unique(array_filter($blobShas)));
        if (empty($blobShas)) {
            return [];
        }

        Log::info('GitService.getBlobs start', [
            'count' => count($blobShas),
        ]);

        $results = [];
        $client = new Client([
            'base_uri' => 'https://api.github.com/',
            'headers' => [
                'Authorization' => "token {$this->githubToken}",
                'Accept' => 'application/vnd.github.v3+json',
            ],
            // Reasonable timeout to avoid long hangs
            'timeout' => 20,
            'http_errors' => false,
        ]);

        $requests = function () use ($blobShas) {
            foreach ($blobShas as $sha) {
                $uri = "repos/{$this->githubOwner}/{$this->githubRepo}/git/blobs/{$sha}";
                yield $sha => new Request('GET', $uri);
            }
        };

        $concurrency = 20;
        $tPoolStart = microtime(true);
        $pool = new Pool($client, $requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => function ($response, $sha) use (&$results) {
                $status = $response->getStatusCode();
                if ($status === 200) {
                    $json = json_decode((string) $response->getBody(), true);
                    $results[$sha] = $json;
                } else {
                    Log::error('GitHub API Error - Get Blob (guzzle pool): status '.$status);
                    $results[$sha] = null;
                }
            },
            'rejected' => function ($reason, $sha) use (&$results) {
                Log::error('GitHub API Error - Get Blob (guzzle pool rejected): '.(string) $reason);
                $results[$sha] = null;
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();
        Log::info('GitService.getBlobs pool_done', [
            'count' => count($blobShas),
            'pool_ms' => (int) round((microtime(true) - $tPoolStart) * 1000),
            'concurrency' => $concurrency,
        ]);

        Log::info('GitService.getBlobs end', [
            'total_ms' => (int) round((microtime(true) - $t0) * 1000),
            'success' => count(array_filter($results, fn ($v) => is_array($v))),
            'failed' => count($blobShas) - count(array_filter($results, fn ($v) => is_array($v))),
        ]);

        return $results;
    }

    /**
     * Get blob SHAs for given file paths at refs (branch or commit SHA) concurrently.
     * items: array<int, array{key: string, ref: string, path: string}>
     * returns: array<string, ?string> key => sha|null
     */
    public function getBlobShasByPaths(array $items): array
    {
        $t0 = microtime(true);
        // Normalize and de-duplicate by key
        $normalized = [];
        foreach ($items as $item) {
            if (! isset($item['key'], $item['ref'], $item['path'])) {
                continue;
            }
            $normalized[$item['key']] = [
                'ref' => (string) $item['ref'],
                'path' => (string) $item['path'],
            ];
        }
        if (empty($normalized)) {
            return [];
        }

        $encodePath = function (string $path): string {
            $segments = array_map('rawurlencode', array_filter(explode('/', trim($path, '/')), fn ($s) => $s !== ''));

            return implode('/', $segments);
        };

        $client = new Client([
            'base_uri' => 'https://api.github.com/',
            'headers' => [
                'Authorization' => "token {$this->githubToken}",
                'Accept' => 'application/vnd.github.v3+json',
            ],
            'timeout' => 20,
            'http_errors' => false,
        ]);

        $requests = function () use ($normalized, $encodePath) {
            foreach ($normalized as $key => $info) {
                $ref = $info['ref'];
                $path = $encodePath($info['path']);
                $uri = "repos/{$this->githubOwner}/{$this->githubRepo}/contents/{$path}?ref=".rawurlencode($ref);
                yield $key => new Request('GET', $uri);
            }
        };

        $results = [];
        $concurrency = 30;
        $tPool = microtime(true);
        $pool = new Pool($client, $requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => function ($response, $key) use (&$results) {
                $status = $response->getStatusCode();
                if ($status === 200) {
                    $json = json_decode((string) $response->getBody(), true);
                    // File response contains 'sha'. Directory returns an array (which we don't expect)
                    $results[$key] = is_array($json) && isset($json['sha']) ? ($json['sha'] ?? null) : ($json['sha'] ?? null);
                } elseif ($status === 404) {
                    // File not found at ref
                    $results[$key] = null;
                } else {
                    Log::error('GitHub API Error - Get Content (sha) status '.$status);
                    $results[$key] = null;
                }
            },
            'rejected' => function ($reason, $key) use (&$results) {
                Log::error('GitHub API Error - Get Content (sha) rejected: '.(string) $reason);
                $results[$key] = null;
            },
        ]);

        $pool->promise()->wait();

        Log::info('GitService.getBlobShasByPaths done', [
            'count' => count($normalized),
            'pool_ms' => (int) round((microtime(true) - $tPool) * 1000),
            'total_ms' => (int) round((microtime(true) - $t0) * 1000),
            'success' => count(array_filter($results, fn ($v) => is_string($v) && $v !== '')),
        ]);

        return $results;
    }

    /**
     * ブランチをbaseブランチにマージ（upstream sync）
     */
    public function mergeUpstream(string $headBranch, string $baseBranch = 'main'): array
    {
        $url = "https://api.github.com/repos/{$this->githubOwner}/{$this->githubRepo}/merges";

        $response = Http::withHeaders([
            'Authorization' => "token {$this->githubToken}",
            'Accept' => 'application/vnd.github.v3+json',
        ])->post($url, [
            'base' => $headBranch,     // マージ先（PRブランチ）
            'head' => $baseBranch,     // マージ元（mainブランチ）
            'commit_message' => "Merge {$baseBranch} into {$headBranch}",
        ]);

        if (! $response->successful()) {
            $responseBody = $response->body();
            Log::error('GitHub API Error - Merge Upstream: '.$responseBody);

            // すでに最新の場合は例外を投げない
            $responseData = json_decode($responseBody, true);
            if (isset($responseData['message'])) {
                $message = $responseData['message'];
                if (strpos($message, 'Nothing to merge') !== false ||
                    strpos($message, 'already up-to-date') !== false ||
                    strpos($message, 'up to date') !== false) {
                    return ['message' => 'Already up to date'];
                }
            }

            throw new \Exception('アップストリームマージに失敗しました: '.$responseBody);
        }

        $result = $response->json();

        // レスポンスがnullの場合のフォールバック
        if ($result === null) {
            return ['message' => 'Merge completed'];
        }

        return $result;
    }
}
