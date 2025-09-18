<?php

namespace App\Dto\UseCase\DocumentCategory;

use App\Dto\UseCase\UseCaseDto;

/**
 * カテゴリ詳細取得DTO
 */
class GetCategoryDto extends UseCaseDto
{
    /**
     * カテゴリID
     */
    public readonly int $id;

    /**
     * ユーザー
     */
    public readonly object $user;

    /**
     * コンストラクタ
     */
    public function __construct(array $data)
    {
        $this->id = $data['id'];
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
