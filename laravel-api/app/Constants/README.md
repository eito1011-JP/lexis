# 定数ファイルの使用方法

このディレクトリには、アプリケーション全体で使用される定数を定義しています。

## DocumentCategoryConstants

ドキュメントカテゴリに関連する定数を管理します。

### 使用方法

```php
use App\Constants\DocumentCategoryConstants;

// デフォルトカテゴリIDの使用
$defaultCategoryId = DocumentCategoryConstants::DEFAULT_CATEGORY_ID;

// デフォルトカテゴリスラッグの使用
$defaultCategorySlug = DocumentCategoryConstants::DEFAULT_CATEGORY_SLUG;
```

### 定義されている定数

- `DEFAULT_CATEGORY_ID`: デフォルトカテゴリ（未分類）のID (1)
- `DEFAULT_CATEGORY_SLUG`: デフォルトカテゴリのスラッグ ('uncategorized')
- `DEFAULT_CATEGORY_NAME`: デフォルトカテゴリの名前 ('未分類')
- `DEFAULT_CATEGORY_SIDEBAR_LABEL`: デフォルトカテゴリのサイドバーラベル ('未分類')

## 新しい定数の追加

新しい定数を追加する場合は、以下の点に注意してください：

1. 適切な名前空間を使用する
2. 定数名は大文字のスネークケースを使用する
3. PHPDocコメントで説明を追加する
4. 関連する定数は同じクラスにまとめる

### 例

```php
<?php

namespace App\Constants;

/**
 * 新しい定数クラスの例
 */
class NewConstants
{
    /**
     * 新しい定数の説明
     */
    public const NEW_CONSTANT = 'value';
}
``` 