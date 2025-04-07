import { createClient } from '@libsql/client';

// 環境変数で接続先を切り替える
const DB_URL =
  process.env.NODE_ENV === 'production'
    ? process.env.TURSO_DB_URL // 本番: リモートTurso DB
    : 'http://127.0.0.1:8080'; // 開発: ローカルTurso CLI

const AUTH_TOKEN =
  process.env.NODE_ENV === 'production'
    ? process.env.TURSO_AUTH_TOKEN // 本番: 認証トークン
    : undefined; // 開発: 不要

// データベースクライアントの作成
export const db = createClient({
  url: DB_URL,
  authToken: AUTH_TOKEN,
});
