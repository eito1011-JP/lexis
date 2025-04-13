CREATE TABLE IF NOT EXISTS users (
  id TEXT PRIMARY KEY,
  email TEXT UNIQUE NOT NULL,
  password TEXT NOT NULL,
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS sessions (
  id TEXT PRIMARY KEY,
  user_id TEXT REFERENCES users(id) ON DELETE CASCADE,
  sess TEXT NOT NULL,
  expired_at DATETIME NOT NULL
);

CREATE INDEX IF NOT EXISTS sessions_expired_idx ON sessions (expired_at);

-- ユーザーブランチ管理テーブル
CREATE TABLE IF NOT EXISTS user_branches (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL,
  branch_name TEXT NOT NULL,
  is_active BOOLEAN DEFAULT 1,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  pr_status TEXT DEFAULT 'none', -- none, pending, created
  pr_url TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- インデックス作成
CREATE INDEX IF NOT EXISTS idx_user_branches_user_id ON user_branches(user_id);
CREATE INDEX IF NOT EXISTS idx_user_branches_active ON user_branches(is_active);
