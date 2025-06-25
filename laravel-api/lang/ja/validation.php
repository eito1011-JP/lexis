<?php

return [
    'email' => [
        'required' => 'メールアドレスは必須です',
        'email' => '有効なメールアドレスを入力してください',
        'unique' => 'このメールアドレスは既に登録されています',
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
    'attributes' => [
        'name' => 'カテゴリ名',
        'slug' => 'スラッグ',
        'sidebarLabel' => 'サイドバーラベル',
        'position' => '位置',
        'description' => '説明',
        'categoryPath' => 'カテゴリパス',
    ],
    'unique_slug_in_same_parent' => ':slugは既に同じカテゴリ内で使われています',
];
