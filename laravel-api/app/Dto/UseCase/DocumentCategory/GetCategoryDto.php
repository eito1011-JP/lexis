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
     * コンストラクタ
     */
    public function __construct(array $data)
    {
        $this->categoryEntityId = $data['category_entity_id'];
        $this->user = $data['user'];
    }

    /**
     * リクエストからDTOを作成
     */
    public static function fromRequest(array $data): self
    {
        return new self($data);
    }
}
