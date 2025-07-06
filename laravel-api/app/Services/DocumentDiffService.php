<?php

namespace App\Services;

use App\Enums\EditStartVersionTargetType;
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

            // 現在のオブジェクトを追加
            if ($currentObject) {
                if ($isDocument) {
                    $documentVersions->push($currentObject);
                } else {
                    $documentCategories->push($currentObject);
                }
            }

            Log::info('isNewCreation: '.$isNewCreation);
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
            return 'create';
        }

        if ($currentObject && property_exists($currentObject, 'is_deleted') && $currentObject->is_deleted) {
            return 'delete';
        }

        return 'update';
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
                'sidebar_label' => ['status' => 'removed', 'current' => null, 'original' => $originalDocument->sidebar_label],
                'slug' => ['status' => 'removed', 'current' => null, 'original' => $originalDocument->slug],
                'content' => ['status' => 'removed', 'current' => null, 'original' => $originalDocument->content],
                'category_id' => ['status' => 'removed', 'current' => null, 'original' => $originalDocument->category_id],
                'file_order' => ['status' => 'removed', 'current' => null, 'original' => $originalDocument->file_order],
                'is_public' => ['status' => 'removed', 'current' => null, 'original' => $originalDocument->status === 'published'],
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
                'sidebar_label' => ['status' => 'added', 'current' => $currentCategory->sidebar_label, 'original' => null],
                'slug' => ['status' => 'added', 'current' => $currentCategory->slug, 'original' => null],
                'description' => ['status' => 'added', 'current' => $currentCategory->description, 'original' => null],
                'position' => ['status' => 'added', 'current' => $currentCategory->position, 'original' => null],
                'parent_id' => ['status' => 'added', 'current' => $currentCategory->parent_id, 'original' => null],
            ];
        }

        if ($currentCategory && property_exists($currentCategory, 'is_deleted') && $currentCategory->is_deleted) {
            // 削除時は全フィールドが削除対象
            return [
                'sidebar_label' => ['status' => 'removed', 'current' => null, 'original' => $originalCategory->sidebar_label],
                'slug' => ['status' => 'removed', 'current' => null, 'original' => $originalCategory->slug],
                'description' => ['status' => 'removed', 'current' => null, 'original' => $originalCategory->description],
                'position' => ['status' => 'removed', 'current' => null, 'original' => $originalCategory->position],
                'parent_id' => ['status' => 'removed', 'current' => null, 'original' => $originalCategory->parent_id],
            ];
        }

        // 編集時は変更されたフィールドのみを検出
        $changedFields = [];
        $fieldsToCheck = ['sidebar_label', 'slug', 'description', 'position', 'parent_id'];

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
}
