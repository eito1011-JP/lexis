#!/usr/bin/env bash
set -euo pipefail

### ===== 設定 =====
APP_ROOT="$(pwd)"                          # プロジェクトのルート

# .env.deployファイルから設定を読み込み
ENV_DEPLOY_FILE="${APP_ROOT}/.env.deploy"
if [[ ! -f "${ENV_DEPLOY_FILE}" ]]; then
  echo "エラー: ${ENV_DEPLOY_FILE} が見つかりません" >&2
  echo "以下の内容で .env.deploy ファイルを作成してください:" >&2
  echo "HOST=\"your-ec2-ip\"" >&2
  echo "USER=\"ec2-user\"" >&2
  echo "KEY=\"\$HOME/.ssh/your-key.pem\"" >&2
  exit 1
fi

# .env.deployファイルを読み込み
source "${ENV_DEPLOY_FILE}"

# 必要な変数が設定されているかチェック
if [[ -z "${HOST:-}" ]]; then
  echo "エラー: HOST が .env.deploy で設定されていません" >&2
  exit 1
fi
if [[ -z "${USER:-}" ]]; then
  echo "エラー: USER が .env.deploy で設定されていません" >&2
  exit 1
fi
if [[ -z "${KEY:-}" ]]; then
  echo "エラー: KEY が .env.deploy で設定されていません" >&2
  exit 1
fi

# エラーハンドリング用関数
cleanup_on_error() {
  echo "エラーが発生しました。デプロイを中断します..." >&2
  exit 1
}
trap cleanup_on_error ERR

# ディレクトリ設定
BACKEND_DIR="${APP_ROOT}/laravel-api"
FRONT_DIR="${APP_ROOT}/frontend"
ENV_FILE="${APP_ROOT}/laravel-api/.env.staging"

REMOTE_BASE="/var/www/lexis"
RELEASES_DIR="${REMOTE_BASE}/releases"
TS="$(date -u +%Y%m%d-%H%M%S)"
REMOTE_RELEASE="${RELEASES_DIR}/${TS}"

# SSH 共通オプション
SSH_OPTS=(-i "${KEY}" -o StrictHostKeyChecking=accept-new)

# Laravel .env.staging ファイルの存在確認
if [[ ! -f "${ENV_FILE}" ]]; then
  echo "エラー: ${ENV_FILE} が見つかりません" >&2
  echo "Laravel用の .env.staging ファイルを作成してください" >&2
  exit 1
fi

echo "== Laravel dependency check =="
cd "${BACKEND_DIR}"
if [[ ! -f "composer.json" ]]; then
  echo "エラー: composer.json が見つかりません。Laravel プロジェクトか確認してください" >&2
  exit 1
fi

echo "== Build frontend =="
cd "${FRONT_DIR}"
npm ci
npm run build

echo "== Prepare remote release dir =="
ssh "${SSH_OPTS[@]}" "${USER}@${HOST}" "mkdir -p '${REMOTE_RELEASE}/api' '${REMOTE_RELEASE}/admin'"

echo "== Rsync backend (Laravelソース) =="
rsync -avz --delete \
  --exclude=node_modules \
  --exclude=vendor \
  --exclude=.git \
  --exclude=tests \
  --exclude=storage/framework/views/* \
  --exclude=storage/framework/cache/* \
  --exclude=storage/framework/sessions/* \
  --exclude=storage/logs/* \
  --exclude=storage/app/public/* \
  --exclude=bootstrap/cache/* \
  --exclude=.env \
  --exclude=.env.* \
  --exclude=phpunit.xml \
  --exclude=docker \
  --exclude=docker-compose.yml \
  --exclude=Dockerfile \
  --exclude=*.db \
  --exclude=*.db-* \
  --exclude=*.db-shm \
  --exclude=*.db-wal \
  --exclude=README.md \
  --exclude=local.db* \
  --exclude=.phpunit.result.cache \
  --exclude=package-lock.json \
  --exclude=package.json \
  --exclude=vite.config.js \
  -e "ssh ${SSH_OPTS[*]}" \
  "${BACKEND_DIR}/" "${USER}@${HOST}:${REMOTE_RELEASE}/api/"

echo "== Rsync frontend (ビルド成果物) =="
if [[ -d "${FRONT_DIR}/dist" ]]; then
  FRONT_BUILD_DIR="${FRONT_DIR}/dist"
elif [[ -d "${FRONT_DIR}/build" ]]; then
  FRONT_BUILD_DIR="${FRONT_DIR}/build"
else
  echo "フロントのビルド成果物(dist/ or build/)が見つかりません" >&2
  exit 1
fi

rsync -avz --delete \
  -e "ssh ${SSH_OPTS[*]}" \
  "${FRONT_BUILD_DIR}/" "${USER}@${HOST}:${REMOTE_RELEASE}/admin/"

echo "== Upload .env =="
scp "${SSH_OPTS[@]}" "${ENV_FILE}" "${USER}@${HOST}:${REMOTE_RELEASE}/api/.env"

echo "== Pre-deploy storage directory setup =="
ssh "${SSH_OPTS[@]}" "${USER}@${HOST}" "
  mkdir -p '${REMOTE_RELEASE}/api/storage/app' '${REMOTE_RELEASE}/api/storage/logs' '${REMOTE_RELEASE}/api/storage/framework/cache' '${REMOTE_RELEASE}/api/storage/framework/sessions' '${REMOTE_RELEASE}/api/storage/framework/views' '${REMOTE_RELEASE}/api/bootstrap/cache'
"

echo "== Execute remote finish =="
ssh "${SSH_OPTS[@]}" "${USER}@${HOST}" "sudo /usr/local/bin/lexis-deploy '${REMOTE_RELEASE}'"

echo "== Verify deployment =="
ssh "${SSH_OPTS[@]}" "${USER}@${HOST}" "
  if [[ -L '${REMOTE_BASE}/current' ]] && [[ \$(readlink '${REMOTE_BASE}/current') == '${REMOTE_RELEASE}' ]]; then
    echo 'デプロイ成功: current symlink が正しく設定されました'
  else
    echo 'デプロイ検証失敗: current symlink が正しく設定されていません' >&2
    exit 1
  fi
"

echo "== DONE: ${HOST} に ${TS} リリースを適用しました =="
