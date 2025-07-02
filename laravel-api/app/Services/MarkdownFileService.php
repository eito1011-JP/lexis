<?php

namespace App\Services;

use App\Constants\DocumentPathConstants;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;

class MarkdownFileService
{
    /**
     * ドキュメントバージョンからMarkdownファイルを作成
     */
    public function createDocumentMarkdown(DocumentVersion $documentVersion): string
    {
        $content = '';

        // Front matterを追加
        $content .= "---\n";
        $content .= "sidebar_label: '{$documentVersion->sidebar_label}'\n";
        $content .= "last_edited_by: '{$documentVersion->last_edited_by}'\n";
        $content .= "---\n\n";

        // コンテンツを追加
        $content .= $documentVersion->content;

        return $content;
    }

    /**
     * カテゴリからMarkdownファイルを作成
     */
    public function createCategoryMarkdown(DocumentCategory $category): string
    {
        $content = '';

        // Front matterを追加
        $content .= "---\n";
        $content .= "sidebar_label: '{$category->sidebar_label}'\n";
        if ($category->description) {
            $content .= "description: '{$category->description}'\n";
        }
        $content .= "---\n\n";

        // カテゴリの説明があれば追加
        if ($category->description) {
            $content .= $category->description."\n\n";
        }

        return $content;
    }

    /**
     * ファイルパスを生成
     */
    public function generateFilePath(string $slug, ?string $categoryPath = null): string
    {
        $path = DocumentPathConstants::DOCS_BASE_PATH;

        if ($categoryPath) {
            $path .= '/'.trim($categoryPath, '/');
        }

        $path .= '/'.$slug.DocumentPathConstants::DOCUMENT_FILE_EXTENSION;

        return $path;
    }

    /**
     * カテゴリファイルパスを生成
     */
    public function generateCategoryFilePath(string $slug, ?string $categoryPath = null): string
    {
        $path = DocumentPathConstants::DOCS_BASE_PATH;

        if ($categoryPath) {
            $path .= '/'.trim($categoryPath, '/');
        }

        $path .= '/'.$slug.'/_category.json';

        return $path;
    }

    /**
     * カテゴリJSONコンテンツを生成
     */
    public function createCategoryJson(DocumentCategory $category): string
    {
        $jsonData = [
            'label' => $category->sidebar_label,
            'position' => $category->position,
        ];

        if ($category->description) {
            $jsonData['description'] = $category->description;
        }

        return json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
