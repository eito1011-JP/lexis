CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT UNIQUE NOT NULL,
  password TEXT NOT NULL,
  created_at DATETIME DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS sessions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
  sess TEXT NOT NULL,
  expired_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS user_branches (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  branch_name TEXT NOT NULL,
  snapshot_commit TEXT NOT NULL,
  is_active INTEGER DEFAULT 1,
  pr_status TEXT CHECK(pr_status IN ('none', 'conflict', 'created', 'merged', 'closed')) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT (datetime('now')),
  updated_at DATETIME NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS document_versions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  user_branch_id INTEGER NOT NULL,
  file_path TEXT NOT NULL,
  status TEXT CHECK(status IN ('draft', 'committed', 'pushed', 'merged')) NOT NULL,
  content TEXT,
  original_blob_sha TEXT,
  slug TEXT,
  category TEXT,
  sidebar_label TEXT,
  display_order INTEGER,
  last_edited_by TEXT,
  created_at DATETIME NOT NULL DEFAULT (datetime('now')),
  updated_at DATETIME NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (user_branch_id) REFERENCES user_branches(id) ON DELETE CASCADE
);


CREATE INDEX IF NOT EXISTS idx_document_versions_user ON document_versions(user_id);
CREATE INDEX IF NOT EXISTS idx_document_versions_branch ON document_versions(user_branch_id);
CREATE INDEX IF NOT EXISTS idx_document_versions_file_path ON document_versions(file_path);
CREATE INDEX IF NOT EXISTS idx_document_versions_status ON document_versions(status);

CREATE TABLE IF NOT EXISTS user_branches (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  branch_name TEXT NOT NULL,
  snapshot_commit TEXT NOT NULL,
  is_active INTEGER DEFAULT 1,
  pr_status TEXT CHECK(pr_status IN ('none', 'conflict', 'created', 'merged', 'closed')) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT (datetime('now')),
  updated_at DATETIME NOT NULL DEFAULT (datetime('now')),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_document_versions_user ON document_versions(user_id);
CREATE INDEX IF NOT EXISTS idx_document_versions_branch ON document_versions(user_branch_id);
CREATE INDEX IF NOT EXISTS idx_document_versions_file_path ON document_versions(file_path);
CREATE INDEX IF NOT EXISTS idx_document_versions_status ON document_versions(status);
