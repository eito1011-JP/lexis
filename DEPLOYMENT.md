# Laravel Staging Deployment Guide

## 概要

このガイドでは、LexisのLaravelアプリケーションをステージング環境（EC2）にデプロイする手順を説明します。

## 前提条件

1. **EC2インスタンス**: Amazon Linux 2またはCentOS 7/8
2. **Nginx**: Webサーバーとして設定済み
3. **PHP 8.2+**: Laravel 12の要件
4. **MySQL/MariaDB**: データベースサーバー
5. **Node.js & npm**: フロントエンドビルド用

## 初回セットアップ

### 1. EC2上にデプロイスクリプトを設置

EC2インスタンスに以下のコマンドでデプロイスクリプトを設置：

```bash
# プロジェクトルートにあるlexis-deployファイルをEC2にコピー
scp -i your-key.pem lexis-deploy ec2-user@your-ec2-ip:/tmp/
ssh -i your-key.pem ec2-user@your-ec2-ip "sudo mv /tmp/lexis-deploy /usr/local/bin/ && sudo chmod +x /usr/local/bin/lexis-deploy"
```

または手動で設置する場合：

```bash
sudo tee /usr/local/bin/lexis-deploy >/dev/null <<'EOF'
# lexis-deployファイルの内容をここに貼り付け
EOF

sudo chmod +x /usr/local/bin/lexis-deploy
```

### 2. ローカル環境の設定

#### .env.deployファイルの作成

プロジェクトルートに`.env.deploy`ファイルを作成：

```bash
# .env.deploy
HOST="your-ec2-ip-address"
USER="ec2-user"
KEY="$HOME/.ssh/your-ec2-key.pem"
```

#### .env.stagingファイルの作成

`laravel-api/.env.staging`ファイルを作成：

```bash
# Laravel Application Configuration for Staging Environment
APP_NAME="Lexis Staging"
APP_ENV=staging
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Asia/Tokyo
APP_URL=https://your-staging-domain.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lexis_staging
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# その他の設定...
```

### 3. EC2環境の準備

#### 必要なディレクトリの作成

```bash
sudo mkdir -p /var/www/lexis/releases
sudo chown nginx:nginx /var/www/lexis
```

#### Nginxの設定

Document rootを`/var/www/lexis/current/admin`および`/var/www/lexis/current/api/public`に設定。

## デプロイ手順

### 1. 通常のデプロイ

```bash
./deploy_stg.sh
```

### 2. デプロイの流れ

1. **フロントエンドビルド**: admin-frontend の npm run build
2. **Laravel検証**: composer.json と .env.staging の存在確認
3. **ファイル同期**: rsync でソースコードを転送
4. **リモート処理**: EC2上で lexis-deploy スクリプト実行
   - Composer依存関係インストール
   - APP_KEY生成（必要時）
   - データベースマイグレーション
   - キャッシュクリア・最適化
   - ファイル権限設定
   - シンボリックリンク切り替え
   - Webサーバー再読み込み

### 3. デプロイ後の確認

デプロイスクリプトは自動的に以下を確認します：

- currentシンボリックリンクの正常性
- Laravelアプリケーションの動作確認

## トラブルシューティング

### ロールバック

デプロイ中にエラーが発生した場合、自動的に前のリリースにロールバックされます。

手動でロールバックする場合：

```bash
# EC2上で実行
sudo ln -sfn /var/www/lexis/previous /var/www/lexis/current
sudo systemctl reload nginx
```

### よくある問題

1. **権限エラー**: storage と bootstrap/cache ディレクトリの権限を確認
2. **データベース接続エラー**: .env.staging の DB設定を確認
3. **APP_KEY未設定**: php artisan key:generate --force を実行

### ログの確認

```bash
# Laravel ログ
tail -f /var/www/lexis/current/api/storage/logs/laravel.log

# Nginx ログ
sudo tail -f /var/log/nginx/error.log
```

## ファイル構成

```
/var/www/lexis/
├── current/          # 現在のリリース（シンボリックリンク）
├── previous/         # 前のリリース（シンボリックリンク）
└── releases/         # リリース履歴
    ├── 20250101-120000/
    │   ├── admin/    # フロントエンド（ビルド済み）
    │   └── api/      # Laravel アプリケーション
    └── 20250101-130000/
        ├── admin/
        └── api/
```

## セキュリティ注意事項

1. `.env.staging`に機密情報を含める際は適切な権限設定を行う
2. データベースパスワードは強力なものを使用する
3. HTTPS通信を必須とする
4. 定期的なセキュリティアップデートを実施する

## パフォーマンス最適化

1. **OPcache**: PHP OPcacheを有効化
2. **Laravel最適化**: config:cache, route:cache, view:cache を使用
3. **Nginx最適化**: gzip圧縮、静的ファイルキャッシュを設定
4. **データベース最適化**: インデックスとクエリの最適化
