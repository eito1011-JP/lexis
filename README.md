# lexis

Docusaurusを使用したドキュメント管理システム

## 概要

このプロジェクトは、Docusaurusをベースにしたドキュメント管理システムです。フロントエンドはDocusaurus、バックエンドAPIはLaravelを使用しています。

## アーキテクチャ

- **フロントエンド**: Docusaurus (React)
- **管理画面**: 別のReactアプリケーション
- **バックエンドAPI**: Laravel (PHP)
- **データベース**: SQLite

## セットアップ

### 前提条件

- Node.js (>=18.0)
- PHP (>=8.1)
- Composer
- SQLite

### インストール

1. 依存関係をインストール:
```bash
npm install
cd frontend && npm install
cd ../laravel-api && composer install
```

2. 環境設定:
```bash
cd laravel-api
cp .env.example .env
php artisan key:generate
```

3. データベースマイグレーション:
```bash
cd laravel-api
php artisan migrate
```

### 開発サーバーの起動

```bash
npm run dev
```

このコマンドで以下が起動します:
- Laravel APIサーバー (http://localhost:8000)
- 管理画面 (http://localhost:3002)
- Docusaurus (http://localhost:3000)
- Turso DB

## API エンドポイント

### 認証
- `POST /api/auth/signup` - ユーザー登録
- `POST /api/auth/login` - ログイン
- `GET /api/auth/session` - セッション確認
- `POST /api/auth/logout` - ログアウト

### ドキュメント管理
- `GET /api/admin/documents/categories` - カテゴリ一覧
- `POST /api/admin/documents/categories` - カテゴリ作成
- `PUT /api/admin/documents/categories/{id}` - カテゴリ更新
- `DELETE /api/admin/documents/categories/{id}` - カテゴリ削除
- `GET /api/admin/documents` - ドキュメント一覧
- `POST /api/admin/documents` - ドキュメント作成
- `PUT /api/admin/documents/{id}` - ドキュメント更新
- `DELETE /api/admin/documents/{id}` - ドキュメント削除

### ユーザー管理
- `GET /api/admin/users` - ユーザー一覧

## 移行について

このプロジェクトは元々Express.jsを使用していましたが、Laravelに移行しました。

### 移行の利点
- より豊富な機能（Eloquent ORM、認証、バリデーションなど）
- 構造化されたコード
- 強力なCLIツール（Artisan）
- 豊富なライブラリエコシステム

### プロキシ設定
DocusaurusからLaravel APIへのリクエストは、Webpack DevServerのプロキシ機能を使用して転送されます。

## 開発

### 新しいAPIエンドポイントの追加

1. Laravelコントローラーにメソッドを追加
2. `routes/api.php`にルートを追加
3. 必要に応じてミドルウェアを設定

### データベースの変更

1. マイグレーションファイルを作成: `php artisan make:migration create_table_name`
2. マイグレーションを実行: `php artisan migrate`

## デプロイ

本番環境では、Laravel APIサーバーを適切に設定し、Docusaurusのビルドファイルと共にデプロイしてください。
