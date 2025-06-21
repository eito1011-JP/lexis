#!/bin/bash

# Laravel APIサーバー起動スクリプト

echo "Laravel APIサーバーを起動しています..."

# Laravelディレクトリに移動
cd laravel-api

# 依存関係をインストール（初回のみ）
if [ ! -d "vendor" ]; then
    echo "Composer依存関係をインストールしています..."
    composer install
fi

# 環境設定ファイルをコピー（初回のみ）
if [ ! -f ".env" ]; then
    echo "環境設定ファイルを作成しています..."
    cp .env.example .env
    php artisan key:generate
fi

# データベースマイグレーションを実行
echo "データベースマイグレーションを実行しています..."
php artisan migrate --force

# サーバーを起動
echo "Laravel APIサーバーを起動しています..."
php artisan serve --host=0.0.0.0 --port=8000 