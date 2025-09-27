<?php

namespace App\Services;

use App\Constants\DocumentPathConstants;
use App\Models\DocumentCategory;
use Illuminate\Support\Facades\Log;

class CategoryFolderService
{
    /**
     * カテゴリファイルパスを生成
     */
    public function generateCategoryFilePath(string $slug, ?int $parentId = null): string
    {
        $path = DocumentPathConstants::DOCS_BASE_PATH;
        $parentPath = null;

        // parent_entity_idが指定されている場合、親のパスを再帰的に構築
        if ($parentId) {
            $parentPath = $this->buildParentPath($parentId);
            if ($parentPath) {
                $path .= '/'.$parentPath;
            }
        }

        Log::info('parentPath: '.$parentPath);
        $path .= '/'.$slug;

        Log::info('path: '.$path);

        return $path;
    }

    /**
     * 親カテゴリのパスを再帰的に構築
     */
    private function buildParentPath(int $parentId): ?string
    {
        $category = DocumentCategory::find($parentId);
        if (! $category) {
            return null;
        }

        $path = [];
        $current = $category;

        // 親カテゴリを再帰的に辿ってパスを構築
        while ($current) {
            array_unshift($path, $current->slug);
            $current = $current->parent;
        }

        return implode('/', $path);
    }
}
