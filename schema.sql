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

CREATE TABLE IF NOT EXISTS user_branches (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL,
  branch_name TEXT NOT NULL,
  snapshot_commit TEXT NOT NULL,
  is_active BOOLEAN DEFAULT 1,
  pr_status TEXT CHECK(pr_status IN ('conflict', 'created', 'merged')) NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS document_versions (
  id TEXT PRIMARY KEY,
  user_id TEXT NOT NULL,
  branch_id TEXT NOT NULL,
  file_path TEXT NOT NULL,
  status TEXT CHECK(status IN ('draft', 'committed', 'pushed', 'merged')) NOT NULL,
  content TEXT,
  original_blob_sha TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (branch_id) REFERENCES user_branches(id) ON DELETE CASCADE
);


CREATE INDEX IF NOT EXISTS idx_document_versions_user ON document_versions(user_id);
CREATE INDEX IF NOT EXISTS idx_document_versions_branch ON document_versions(branch_id);
CREATE INDEX IF NOT EXISTS idx_document_versions_file_path ON document_versions(file_path);
CREATE INDEX IF NOT EXISTS idx_document_versions_status ON document_versions(status);
