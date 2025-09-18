<?php

return [
    'email' => 'メールアドレス',
    'document' => [
        'category' => 'カテゴリ',
        'label' => 'ラベル',
        'content' => 'コンテンツ',
        'slug' => 'スラッグ',
        'fileOrder' => 'ファイル順序',
        'isPublic' => '公開設定',
        'sidebarLabel' => 'サイドバーラベル',
        'position' => '位置',
        'description' => '説明',
        'status' => 'ステータス',
        'lastEditedBy' => '最終編集者',
    ],
    'category' => [
        'name' => '名前',
        'slug' => 'スラッグ',
        'sidebarLabel' => 'サイドバーラベル',
        'position' => '位置',
        'description' => '説明',
        'categoryPath' => 'カテゴリパス',
    ],

    // プルリクエスト作成用の属性名
    'organization_id' => '組織ID',
    'user_branch_id' => 'ユーザーブランチID',
    'title' => 'タイトル',
    'description' => '説明',
    'reviewers' => 'レビュアー',
    'reviewers.*' => 'レビュアーのメールアドレス',

    // ドキュメント作成用の属性名
    'category_id' => 'カテゴリID',
    'edit_pull_request_id' => '編集プルリクエストID',
    'pull_request_edit_token' => 'プルリクエスト編集トークン',
];
