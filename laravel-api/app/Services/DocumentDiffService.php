<?php

namespace App\Services;

use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DocumentDiffService
{
    /**
     * 差分データを生成
     */
    public function generateDiffData(Collection $editStartVersions): array
    {
        $documentVersions = collect();
        $documentCategories = collect();
        $originalDocumentVersions = collect();
        $originalDocumentCategories = collect();
        $diffData = collect();

        foreach ($editStartVersions as $editStartVersion) {
            $currentObject = $editStartVersion->getCurrentObject();
            $originalObject = $editStartVersion->getOriginalObject();

            // 同一ブランチで作成されたデータかどうかを判定
            $isNewCreation = $this->isNewCreation($editStartVersion, $currentObject);
            $isDocument = $editStartVersion->target_type === EditStartVersionTargetType::DOCUMENT->value;

            // 同一ブランチで作成されたデータが削除された場合は差分から除外
            if ($isNewCreation && $currentObject && $currentObject->is_deleted) {
                continue;
            }

            // 現在のオブジェクトを追加
            if ($currentObject) {
                if ($isDocument) {
                    $documentVersions->push($currentObject);
                } else {
                    $documentCategories->push($currentObject);
                }
            }

            // 同一ブランチで作成されたデータでない場合のみ元のオブジェクトを追加
            if ($originalObject && ! $isNewCreation) {
                if ($isDocument) {
                    $originalDocumentVersions->push($originalObject);
                } else {
                    $originalDocumentCategories->push($originalObject);
                }
            }

            // 差分情報を生成
            $diffInfo = $this->generateDiffInfo($currentObject, $originalObject, $isDocument, $isNewCreation);
            $diffData->push($diffInfo);
        }

        return [
            'document_versions' => $documentVersions->values()->toArray(),
            'document_categories' => $documentCategories->values()->toArray(),
            'original_document_versions' => $originalDocumentVersions->values()->toArray(),
            'original_document_categories' => $originalDocumentCategories->values()->toArray(),
            'diff_data' => $diffData->toArray(),
        ];
    }

    /**
     * 新規作成かどうかを判定
     */
    private function isNewCreation($editStartVersion, $currentObject): bool
    {
        // original_version_idとcurrent_version_idが同じ場合は新規作成
        if ($editStartVersion->original_version_id === $editStartVersion->current_version_id) {
            return true;
        }

        // 元のオブジェクトが存在しない、または元のオブジェクトも同一ブランチで作成されている場合
        $originalObject = $editStartVersion->getOriginalObject();
        if (! $originalObject || $originalObject->user_branch_id === $currentObject->user_branch_id) {
            return true;
        }

        return false;
    }

    /**
     * 差分情報を生成
     */
    private function generateDiffInfo($currentObject, $originalObject, bool $isDocument, bool $isNewCreation): array
    {
        $diffInfo = [
            'id' => $currentObject->id,
            'type' => $isDocument ? 'document' : 'category',
            'operation' => $this->determineOperation($currentObject, $originalObject, $isNewCreation),
            'changed_fields' => [],
        ];

        if ($isDocument) {
            $diffInfo['changed_fields'] = $this->getDocumentChangedFields($currentObject, $originalObject, $isNewCreation);
        } else {
            $diffInfo['changed_fields'] = $this->getCategoryChangedFields($currentObject, $originalObject, $isNewCreation);
        }

        return $diffInfo;
    }

    /**
     * 操作タイプを判定
     */
    private function determineOperation($currentObject, $originalObject, bool $isNewCreation): string
    {
        if ($isNewCreation) {
            return 'created';
        }

        Log::info('currentObject: '.json_encode($currentObject));
        Log::info('is_deleted: '.json_encode($currentObject->is_deleted));
        if ($currentObject && $currentObject->is_deleted) {
            return 'deleted';
        }

        return 'updated';
    }

    /**
     * ドキュメントの変更フィールドを取得
     */
    private function getDocumentChangedFields($currentDocument, $originalDocument, bool $isNewCreation): array
    {
        if ($isNewCreation) {
            // 新規作成時は全フィールドが変更対象（緑色で表示）
            return [
                'sidebar_label' => ['status' => 'added', 'current' => $currentDocument->sidebar_label, 'original' => null],
                'slug' => ['status' => 'added', 'current' => $currentDocument->slug, 'original' => null],
                'content' => ['status' => 'added', 'current' => $currentDocument->content, 'original' => null],
                'category_id' => ['status' => 'added', 'current' => $currentDocument->category_id, 'original' => null],
                'file_order' => ['status' => 'added', 'current' => $currentDocument->file_order, 'original' => null],
                'is_public' => ['status' => 'added', 'current' => $currentDocument->status === 'published', 'original' => null],
            ];
        }

        if ($currentDocument && property_exists($currentDocument, 'is_deleted') && $currentDocument->is_deleted) {
            // 削除時は全フィールドが削除対象
            return [
                'sidebar_label' => ['status' => 'deleted', 'current' => null, 'original' => $originalDocument->sidebar_label],
                'slug' => ['status' => 'deleted', 'current' => null, 'original' => $originalDocument->slug],
                'content' => ['status' => 'deleted', 'current' => null, 'original' => $originalDocument->content],
                'category_id' => ['status' => 'deleted', 'current' => null, 'original' => $originalDocument->category_id],
                'file_order' => ['status' => 'deleted', 'current' => null, 'original' => $originalDocument->file_order],
                'is_public' => ['status' => 'deleted', 'current' => null, 'original' => $originalDocument->status === 'published'],
            ];
        }

        // 編集時は変更されたフィールドのみを検出
        $changedFields = [];
        $fieldsToCheck = ['sidebar_label', 'slug', 'content', 'category_id', 'file_order'];

        foreach ($fieldsToCheck as $field) {
            if ($currentDocument->{$field} !== $originalDocument->{$field}) {
                $changedFields[$field] = [
                    'status' => 'modified',
                    'current' => $currentDocument->{$field},
                    'original' => $originalDocument->{$field},
                ];
            }
        }

        // is_publicフィールドは特別処理（statusから判定）
        $currentIsPublic = $currentDocument->status === 'published';
        $originalIsPublic = $originalDocument->status === 'published';
        if ($currentIsPublic !== $originalIsPublic) {
            $changedFields['is_public'] = [
                'status' => 'modified',
                'current' => $currentIsPublic,
                'original' => $originalIsPublic,
            ];
        }

        return $changedFields;
    }

    /**
     * カテゴリの変更フィールドを取得
     */
    private function getCategoryChangedFields($currentCategory, $originalCategory, bool $isNewCreation): array
    {
        if ($isNewCreation) {
            // 新規作成時は全フィールドが変更対象（緑色で表示）
            return [
                'title' => ['status' => 'added', 'current' => $currentCategory->title, 'original' => null],
                'description' => ['status' => 'added', 'current' => $currentCategory->description, 'original' => null],
                'parent_id' => ['status' => 'added', 'current' => $currentCategory->parent_id, 'original' => null],
            ];
        }

        if ($currentCategory && property_exists($currentCategory, 'is_deleted') && $currentCategory->is_deleted) {
            // 削除時は全フィールドが削除対象
            return [
                'title' => ['status' => 'deleted', 'current' => null, 'original' => $originalCategory->title],
                'description' => ['status' => 'deleted', 'current' => null, 'original' => $originalCategory->description],
                'parent_id' => ['status' => 'deleted', 'current' => null, 'original' => $originalCategory->parent_id],
            ];
        }

        // 編集時は変更されたフィールドのみを検出
        $changedFields = [];
        $fieldsToCheck = ['title', 'description', 'parent_id'];

        foreach ($fieldsToCheck as $field) {
            if ($currentCategory->{$field} !== $originalCategory->{$field}) {
                $changedFields[$field] = [
                    'status' => 'modified',
                    'current' => $currentCategory->{$field},
                    'original' => $originalCategory->{$field},
                ];
            }
        }

        return $changedFields;
    }

    /**
     * ドキュメントに変更があるかをチェック
     */
    public function hasDocumentChanges(UpdateDocumentDto $dto, DocumentVersion $existingDocument): bool
    {
        $noContentChange = $dto->content === $existingDocument->content;
        $noSlugChange = $dto->slug === $existingDocument->slug;
        $noSidebarLabelChange = $dto->sidebar_label === $existingDocument->sidebar_label;
        $noIsPublicChange = $dto->is_public === $existingDocument->is_public;
        $noFileOrderChange = $dto->file_order === $existingDocument->file_order;

        return ! ($noContentChange && $noSlugChange && $noSidebarLabelChange && $noIsPublicChange && $noFileOrderChange);
    }
}
