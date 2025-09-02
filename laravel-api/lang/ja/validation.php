<?php

return [
    'email' => [
        'required' => 'メールアドレスは必須です',
        'email' => '有効なメールアドレスを入力してください',
        'unique' => 'このメールアドレスは既に登録されています',
        'string' => 'メールアドレスは文字列で入力してください。',
        'max' => 'メールアドレスは255文字以内で入力してください。',
    ],
    'password' => [
        'required' => 'パスワードは必須です',
        'min' => 'パスワードは8文字以上である必要があります',
    ],
    'document' => [
        'id' => [
            'required' => 'ドキュメントIDは必須です',
            'integer' => 'ドキュメントIDは数値である必要があります',
            'exists' => '指定されたドキュメントが見つかりません',
        ],
        'category' => [
            'required' => 'カテゴリは必須です',
        ],
        'label' => [
            'required' => 'ラベルは必須です',
            'max' => 'ラベルは255文字以内で入力してください',
        ],
        'content' => [
            'required' => 'コンテンツは必須です',
        ],
        'slug' => [
            'required' => 'スラッグは必須です',
            'unique' => 'このスラッグは既に使用されています',
            'regex' => 'スラッグは英小文字、数字、ハイフンのみ使用できます',
        ],
        'file_order' => [
            'integer' => 'ファイル順序は数値で入力してください',
            'min' => 'ファイル順序は1以上の有効な整数で入力してください',
        ],
    ],
    'category' => [
        'name' => [
            'required' => 'カテゴリ名は必須です',
            'max' => 'カテゴリ名は255文字以内で入力してください',
        ],
        'slug' => [
            'required' => 'slug is required and must be a string',
            'unique' => 'このスラッグは既に使用されています',
            'string' => 'slug is required and must be a string',
        ],
        'sidebarLabel' => [
            'required' => 'sidebarLabel is required and must be a string',
            'string' => 'sidebarLabel is required and must be a string',
            'max' => 'サイドバーラベルは255文字以内で入力してください',
        ],
        'position' => [
            'integer' => 'position must be a number',
            'numeric' => 'position must be a number',
        ],
        'description' => [
            'string' => 'description must be a string',
        ],
        'categoryPath' => [
            'string' => 'カテゴリパスは文字列である必要があります',
            'max' => 'カテゴリパスは1000文字以内である必要があります',
        ],
    ],
    'user_branch' => [
        'id' => [
            'required' => 'ユーザーブランチIDは必須です',
            'integer' => 'ユーザーブランチIDは整数である必要があります',
            'exists' => '指定されたユーザーブランチが存在しません',
        ],
    ],
    'pull_request' => [
        'id' => [
            'required' => 'プルリクエストIDは必須です',
            'integer' => 'プルリクエストIDは整数である必要があります',
            'exists' => '指定されたプルリクエストが存在しません',
        ],
        'pull_request_id' => [
            'required' => 'プルリクエストIDは必須です',
            'integer' => 'プルリクエストIDは整数である必要があります',
            'exists' => '指定されたプルリクエストが存在しません',
        ],
        'user_branch_id' => [
            'required' => 'ユーザーブランチIDは必須です',
            'integer' => 'ユーザーブランチIDは整数である必要があります',
            'exists' => '指定されたユーザーブランチが存在しません',
        ],
        'title' => [
            'required' => 'プルリクエストタイトルは必須です',
            'string' => 'プルリクエストタイトルは文字列である必要があります',
            'max' => 'プルリクエストタイトルは255文字以内で入力してください',
        ],
        'description' => [
            'nullable' => 'プルリクエスト本文は任意です',
            'string' => 'プルリクエスト本文は文字列である必要があります',
        ],
        'diff_items' => [
            'required' => '差分アイテムは必須です',
            'array' => '差分アイテムは配列である必要があります',
        ],
        'diff_items.*.id' => [
            'required' => 'アイテムIDは必須です',
            'integer' => 'アイテムIDは整数である必要があります',
        ],
        'diff_items.*.type' => [
            'required' => 'アイテムタイプは必須です',
            'string' => 'アイテムタイプは文字列である必要があります',
            'in' => 'アイテムタイプはdocumentまたはcategoryである必要があります',
        ],
        'reviewers' => [
            'nullable' => 'レビュアーは任意です',
            'array' => 'レビュアーは配列である必要があります',
            'max' => 'レビュアーは最大15人まで指定できます',
        ],
        'reviewers.*' => [
            'required' => 'レビュアーのメールアドレスは必須です',
            'string' => 'レビュアーのメールアドレスは文字列である必要があります',
            'email' => '有効なメールアドレスを入力してください',
            'exists' => '指定されたメールアドレスのユーザーが存在しません',
        ],
    ],
    'user' => [
        'id' => [
            'required' => 'ユーザーIDは必須です',
            'integer' => 'ユーザーIDは整数である必要があります',
            'exists' => '指定されたユーザーが存在しません',
        ],
    ],
    'action' => [
        'required' => 'アクション値は必須です',
        'string' => 'アクション値は文字列である必要があります',
        'in' => 'アクション値にはpendingを指定してください',
    ],
    'token' => [
        'required' => 'トークンは必須です',
        'string' => 'トークンは文字列である必要があります',
        'size' => 'トークンは32文字である必要があります',
        'max' => 'トークンは255文字以内で入力してください',
    ],
    'attributes' => [
        'name' => 'カテゴリ名',
        'slug' => 'スラッグ',
        'sidebarLabel' => 'サイドバーラベル',
        'position' => '位置',
        'description' => '説明',
        'categoryPath' => 'カテゴリパス',
        'id' => 'プルリクエストID',
        'user_branch_id' => 'ユーザーブランチID',
        'title' => 'プルリクエストタイトル',
        'diff_items' => '差分アイテム',
        'diff_items.*.id' => 'アイテムID',
        'diff_items.*.type' => 'アイテムタイプ',
        'reviewers' => 'レビュアー',
        'reviewers.*' => 'レビュアーのメールアドレス',
        'token' => 'トークン',
        'pull_request_id' => 'プルリクエストID',
    ],
    'comment' => [
        'content' => [
            'required' => 'コメントは必須です',
            'string' => 'コメントは文字列で入力してください',
            'max' => 'コメントは65535文字以内で入力してください',
        ],
        'pull_request_id' => [
            'required' => 'プルリクエストIDは必須です',
            'integer' => 'プルリクエストIDは数値で入力してください',
            'exists' => '指定されたプルリクエストが見つかりません',
        ],
    ],
    'unique_slug_in_same_parent' => ':slugは既に同じカテゴリ内で使われています',
    'invalid_token' => 'トークンが無効です',
    'organization_duplicate' => 'この組織は既に登録されています',
];
