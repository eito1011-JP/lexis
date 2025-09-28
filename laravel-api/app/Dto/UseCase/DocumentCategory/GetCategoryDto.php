<?php

namespace App\Dto\UseCase\DocumentCategory;

use App\Dto\UseCase\UseCaseDto;

/**
 * カテゴリ詳細取得DTO
 */
class GetCategoryDto extends UseCaseDto
{
    /**
     * カテゴリエンティティID
     */
    public readonly int $categoryEntityId;

    /**
     * ユーザー
     */
    public readonly object $user;

    /**
     * プルリクエスト編集セッショントークン
     */
    public readonly ?string $pullRequestEditSessionToken;

    /**
     * コンストラクタ
     */
    public function __construct(array $data)
    {
        $this->categoryEntityId = $data['category_entity_id'];
        $this->user = $data['user'];
        $this->pullRequestEditSessionToken = $data['pull_request_edit_session_token'] ?? null;
    }

    /**
     * リクエストからDTOを作成
     */
    public static function fromRequest(array $data): self
    {
        return new self($data);
    }
}
