<?php

namespace App\Services;

use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\PullRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PullRequestConflictService
{
    protected GitService $gitService;

    protected MdFileService $mdFileService;

    protected CategoryFolderService $categoryFolderService;

    /**
     * キャッシュ: pullRequestIdごとの直近生成結果
     * value: [ 'generated_at' => float, 'result' => array ]
     */
    protected static array $conflictDiffCache = [];

    public function __construct(GitService $gitService, MdFileService $mdFileService, CategoryFolderService $categoryFolderService)
    {
        $this->gitService = $gitService;
        $this->mdFileService = $mdFileService;
        $this->categoryFolderService = $categoryFolderService;
    }

    /**
     * コンフリクト時の3-way比較データを取得
     *
     * @return array{files: array<int, array{filename: string, status: string, ancestorText: ?string, baseText: ?string, headText: ?string}>}
     */
    public function fetchConflictDiffData(int $pullRequestId): array
    {
        // キャッシュ利用（120秒）
        $cacheKey = 'pr_conflict_diff:'.$pullRequestId;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $start = microtime(true);
        $last = $start;
        $mark = function (string $label) use (&$last, $start) {
            $now = microtime(true);
            Log::info('PullRequestConflictService timing', [
                'label' => $label,
                'step_ms' => (int) round(($now - $last) * 1000),
                'total_ms' => (int) round(($now - $start) * 1000),
            ]);
            $last = $now;
        };

        Log::info('fetchConflictDiffData start', ['pullRequestId' => $pullRequestId]);
        $pullRequest = PullRequest::with('userBranch')->findOrFail($pullRequestId);

        $prInfo = $this->gitService->getPullRequestInfo($pullRequest->pr_number);
        $mark('getPullRequestInfo');
        if ($prInfo['mergeable'] !== false) {
            throw new \InvalidArgumentException('コンフリクトが検出されていないため差分を取得できません');
        }

        $baseBranch = config('services.github.base_branch', 'main');
        $headBranch = $pullRequest->userBranch->branch_name;

        // compare から merge_base と対象ファイルを取得
        $compare = $this->gitService->compareCommits($baseBranch, $headBranch);
        $mark('compareCommits');

        $mergeBaseSha = $compare['merge_base_commit']['sha'] ?? null;
        if (! $mergeBaseSha) {
            throw new \RuntimeException('merge_base_commitが取得できませんでした');
        }

        $files = $compare['files'] ?? [];
        $targetFiles = array_values(array_filter($files, function ($file) {
            $filename = $file['filename'] ?? '';
            if (strpos($filename, 'docs/') !== 0) {
                return false;
            }
            if (substr($filename, -8) === '.gitkeep') {
                return false;
            }

            return true;
        }));

        // Build local pending overlay (virtualHead) from edit_start_versions (documents & categories)
        $editStartVersions = $pullRequest->userBranch->editStartVersions()
            ->with([
                'currentDocumentVersion.category',
                'originalDocumentVersion.category',
                'currentCategory',
                'originalCategory',
            ])->get();

        $localOverlayMap = [];
        foreach ($editStartVersions as $esv) {
            if ($esv->target_type === 'document') {
                /** @var DocumentVersion|null $current */
                $current = $esv->currentDocumentVersion;
                if (! $current) {
                    continue;
                }

                $currentCategoryPath = $current->category ? $current->category->parent_path : null;
                $currentPath = $this->mdFileService->generateFilePath($current->slug, $currentCategoryPath);

                $original = $esv->originalDocumentVersion;
                $originalCategoryPath = $original && $original->category ? $original->category->parent_path : null;
                $originalPath = $original ? $this->mdFileService->generateFilePath($original->slug, $originalCategoryPath) : null;

                // Determine local status (added/modified/deleted/renamed)
                $isNewCreation = ($esv->original_version_id === $esv->current_version_id)
                    || (! $original)
                    || ($original && $current && $original->user_branch_id === $current->user_branch_id);
                $status = $isNewCreation ? 'added' : 'modified';
                if ($current->is_deleted) {
                    $status = 'deleted';
                } elseif ($originalPath && $originalPath !== $currentPath) {
                    $status = 'renamed';
                }

                if (strpos($currentPath, 'docs/') !== 0) {
                    continue;
                }

                $content = $current->is_deleted ? null : $this->mdFileService->createMdFileContent($current);
                $localOverlayMap[$currentPath] = [
                    'status' => $status,
                    'content' => $content,
                    'previous' => $originalPath,
                ];

                continue;
            }

            if ($esv->target_type === 'category') {
                /** @var DocumentCategory|null $currentCat */
                $currentCat = $esv->currentCategory;
                if (! $currentCat) {
                    continue;
                }

                // Folder path for category
                $currentFolder = $this->categoryFolderService->generateCategoryFilePath($currentCat->slug, $currentCat->parent_entity_id);
                $currentPath = rtrim($currentFolder, '/').'/_category_.json';

                $originalCat = $esv->originalCategory;
                $originalFolder = $originalCat
                    ? $this->categoryFolderService->generateCategoryFilePath($originalCat->slug, $originalCat->parent_entity_id)
                    : null;
                $originalPath = $originalFolder ? rtrim($originalFolder, '/').'/_category_.json' : null;

                // Determine status
                $isNewCreation = ($esv->original_version_id === $esv->current_version_id) || (! $originalCat);
                $status = $isNewCreation ? 'added' : 'modified';
                if (property_exists($currentCat, 'is_deleted') && $currentCat->is_deleted) {
                    $status = 'deleted';
                } elseif ($originalPath && $originalPath !== $currentPath) {
                    $status = 'renamed';
                }

                if (strpos($currentPath, 'docs/') !== 0) {
                    continue;
                }

                // Build content for _category_.json compatible with PR creation
                $categoryJsonData = [
                    'label' => $currentCat->sidebar_label,
                    'position' => $currentCat->position,
                ];
                if ($currentCat->description) {
                    $categoryJsonData['link'] = [
                        'type' => 'generated-index',
                        'description' => $currentCat->description,
                    ];
                }
                $content = ($status === 'deleted') ? null : json_encode($categoryJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                $localOverlayMap[$currentPath] = [
                    'status' => $status,
                    'content' => $content,
                    'previous' => $originalPath,
                ];

                continue;
            }
        }

        // Merge local-only files into targetFiles (so that they appear in the result set)
        $existingTargetByPath = [];
        foreach ($targetFiles as $tf) {
            $p = $tf['filename'] ?? null;
            if ($p) {
                $existingTargetByPath[$p] = true;
            }
        }
        foreach ($localOverlayMap as $path => $info) {
            if (! isset($existingTargetByPath[$path])) {
                $targetFiles[] = [
                    'filename' => $path,
                    'status' => $info['status'] ?? 'modified',
                    'previous_filename' => $info['previous'] ?? null,
                ];
            }
        }

        // compare結果の対象ファイルに限定して、各refのblob shaを直接取得（ツリー走査をスキップ）
        $paths = [];
        foreach ($targetFiles as $file) {
            $filename = $file['filename'] ?? '';
            if ($filename !== '') {
                $paths[$filename] = true;
            }
            $prev = $file['previous_filename'] ?? null;
            if ($prev) {
                $paths[$prev] = true;
            }
        }
        $mark('collectPaths');

        // refs: merge-base (commit), base/head (branch)
        $shaRequests = [];
        foreach (array_keys($paths) as $path) {
            $shaRequests[] = ['key' => 'ancestor:'.$path, 'ref' => $mergeBaseSha, 'path' => $path];
            $shaRequests[] = ['key' => 'base:'.$path, 'ref' => $baseBranch, 'path' => $path];
            $shaRequests[] = ['key' => 'head:'.$path, 'ref' => $headBranch, 'path' => $path];
        }

        // 小規模（<= 10ファイル）の場合はContents RAWを直GETして高速化
        $isSmall = count($paths) <= 10;
        $useRaw = $isSmall;

        $blobShaMap = [];
        $rawMap = [];
        if ($useRaw) {
            // 3系統を一気に並列取得
            $rawMap = $this->gitService->getContentsRawBatch($shaRequests);
            $mark('getContentsRawBatch');
        } else {
            $blobShaMap = $this->gitService->getBlobShasByPaths($shaRequests);
            $mark('getBlobShasByPaths');
        }

        // fileごとに ancestor/base/head のSHAを構築
        $fileToShas = [];
        $allBlobShas = [];
        foreach ($targetFiles as $file) {
            $status = $file['status'] ?? '';
            $filename = $file['filename'] ?? '';
            $previous = $file['previous_filename'] ?? null;
            $ancestorPath = ($status === 'renamed' && $previous) ? $previous : $filename;

            if ($useRaw) {
                $fileToShas[] = [
                    'file' => $file,
                    'raw' => [
                        'ancestor' => $rawMap['ancestor:'.$ancestorPath] ?? null,
                        'base' => $rawMap['base:'.$filename] ?? null,
                        'head' => $rawMap['head:'.$filename] ?? null,
                    ],
                ];
            } else {
                $shas = [
                    'ancestor' => $blobShaMap['ancestor:'.$ancestorPath] ?? null,
                    'base' => $blobShaMap['base:'.$filename] ?? null,
                    'head' => $blobShaMap['head:'.$filename] ?? null,
                ];
                $fileToShas[] = ['file' => $file, 'shas' => $shas];
                foreach ($shas as $sha) {
                    if ($sha) {
                        $allBlobShas[] = $sha;
                    }
                }
            }
        }
        $mark($useRaw ? 'collectRaw' : 'collectBlobShas');

        // 重複SHA除去
        if (! $useRaw) {
            $allBlobShas = array_values(array_unique($allBlobShas));
            $mark('dedupeBlobShas');

            $blobMap = $this->gitService->getBlobs($allBlobShas);
            $mark('getBlobs');
        } else {
            $blobMap = [];
        }

        $decode = function ($blob) {
            if (! $blob || ! isset($blob['content'])) {
                return null;
            }
            $content = $blob['content'];
            $content = str_replace(["\n", "\r"], '', $content);
            $decoded = base64_decode($content, true);
            if ($decoded === false) {
                return null;
            }

            return $decoded;
        };

        $responseFiles = [];
        foreach ($fileToShas as $entry) {
            $file = $entry['file'];
            if ($useRaw) {
                $raw = $entry['raw'];
                $ancestorText = is_string($raw['ancestor'] ?? null) ? $raw['ancestor'] : null;
                $baseText = is_string($raw['base'] ?? null) ? $raw['base'] : null;
                $headText = is_string($raw['head'] ?? null) ? $raw['head'] : null;

                // Override headText with local overlay if present
                $fname = $file['filename'] ?? '';
                if (isset($localOverlayMap[$fname])) {
                    $headText = $localOverlayMap[$fname]['content']; // may be null for deleted
                }

                $responseFiles[] = [
                    'filename' => $file['filename'] ?? '',
                    'status' => $file['status'] ?? '',
                    'ancestorText' => $ancestorText,
                    'baseText' => $baseText,
                    'headText' => $headText,
                ];
            } else {
                $shas = $entry['shas'];

                $ancestorText = isset($shas['ancestor']) ? $decode($blobMap[$shas['ancestor']] ?? null) : null;
                $baseText = isset($shas['base']) ? $decode($blobMap[$shas['base']] ?? null) : null;
                $headText = isset($shas['head']) ? $decode($blobMap[$shas['head']] ?? null) : null;

                // Override with local overlay if exists
                $fname = $file['filename'] ?? '';
                if (isset($localOverlayMap[$fname])) {
                    $headText = $localOverlayMap[$fname]['content'];
                }

                $responseFiles[] = [
                    'filename' => $file['filename'] ?? '',
                    'status' => $file['status'] ?? '',
                    'ancestorText' => $ancestorText,
                    'baseText' => $baseText,
                    'headText' => $headText,
                ];
            }
        }
        $mark('buildResponse');

        Log::info('fetchConflictDiffData done', [
            'file_count' => count($responseFiles),
            'use_raw' => $useRaw,
        ]);
        $mark('end');

        $result = [
            'files' => $responseFiles,
        ];

        // キャッシュへ保存
        Cache::put($cacheKey, $result, 120);

        return $result;
    }
}
