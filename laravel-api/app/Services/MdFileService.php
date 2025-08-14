<?php

namespace App\Services;

use App\Constants\DocumentPathConstants;
use App\Consts\Flag;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;

class MdFileService
{
    /**
     * ドキュメントバージョンからMarkdownファイルを作成
     */
    public function createMdFileContent(DocumentVersion $documentVersion): string
    {
        $content = '';

        // Front matterを追加
        $content .= "---\n";
        $content .= "slug: {$documentVersion->slug}\n";

        // カテゴリ情報を追加
        if ($documentVersion->category) {
            $content .= "category: {$documentVersion->category->sidebar_label}\n";
        }

        $content .= "sidebar_label: {$documentVersion->sidebar_label}\n";
        $content .= "file_order: {$documentVersion->file_order}\n";

        // is_publicが0の場合のみdraftフラグを追加
        if ($documentVersion->is_public === Flag::FALSE) {
            $content .= "draft: true\n";
        } else {
            $content .= "draft: false\n";
        }

        // last_edited_byが存在する場合のみ追加
        if ($documentVersion->last_edited_by) {
            $content .= "last_edited_by: {$documentVersion->last_edited_by}\n";
        }

        $content .= "---\n";

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
