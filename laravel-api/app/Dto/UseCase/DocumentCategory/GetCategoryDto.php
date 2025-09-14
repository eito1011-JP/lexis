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
     *
     * @var int
     */
    public readonly int $id;

    /**
     * ユーザー
     *
     * @var object
     */
    public readonly object $user;

    /**
     * コンストラクタ
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->user = $data['user'];  
    }

    /**
     * リクエストからDTOを作成
     *
     * @param array $data
     * @return self
     */
    public static function fromRequest(array $data): self
    {
        return new self($data);
    }
}
