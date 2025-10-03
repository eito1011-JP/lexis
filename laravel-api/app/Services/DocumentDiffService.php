<?php

namespace App\Services;

use App\Dto\UseCase\Document\UpdateDocumentDto;
use App\Enums\EditStartVersionTargetType;
use App\Models\DocumentVersion;
use Illuminate\Support\Collection;

class DocumentDiffService
{
    public function __construct(
        private CategoryService $CategoryService
    ) {}

    /**
     * 差分データを生成
     */
    public function generateDiffData(Collection $editStartVersions): array
    {
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


            // 差分情報を生成
            $diffInfo = $this->generateDiffInfo($currentObject, $originalObject, $isDocument, $isNewCreation);
            $diffData->push($diffInfo);
        }

        return [
            'diff' => $diffData->all(),
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
                'title' => ['status' => 'added', 'current' => $currentDocument->title, 'original' => null],
                'description' => ['status' => 'added', 'current' => $currentDocument->description, 'original' => null],
            ];
        }

        if ($currentDocument && $currentDocument->is_deleted) {
            // 削除時は全フィールドが削除対象
            return [
                // タイトル・説明も削除対象に含める（テストで参照）
                'title' => ['status' => 'deleted', 'current' => null, 'original' => $originalDocument->title],
                'description' => ['status' => 'deleted', 'current' => null, 'original' => $originalDocument->description],
            ];
        }

        // 編集時は変更されたフィールドのみを検出
        $changedFields = [];
        // 互換のためタイトル・説明も検出対象に含める
        $fieldsToCheck = ['title', 'description'];

        foreach ($fieldsToCheck as $field) {
            if ($currentDocument->{$field} !== $originalDocument->{$field}) {
                $changedFields[$field] = [
                    'status' => 'modified',
                    'current' => $currentDocument->{$field},
                    'original' => $originalDocument->{$field},
                ];
            }
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
            ];
        }

        if ($currentCategory && $currentCategory->is_deleted) {
            // 削除時は全フィールドが削除対象
            return [
                'title' => ['status' => 'deleted', 'current' => null, 'original' => $originalCategory->title],
                'description' => ['status' => 'deleted', 'current' => null, 'original' => $originalCategory->description],
            ];
        }

        // 編集時は変更されたフィールドのみを検出
        $changedFields = [];
        $fieldsToCheck = ['title', 'description'];

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
        // DBはtitle/descriptionのみを保持。これらの変更有無で判定
        $noTitleChange = $dto->title === $existingDocument->title;
        $noDescriptionChange = $dto->description === $existingDocument->description;

        return ! ($noTitleChange && $noDescriptionChange);
    }
}
