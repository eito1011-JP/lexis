BEGIN TRANSACTION;
CREATE TABLE IF NOT EXISTS activity_log_on_pull_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  pull_request_id INTEGER NOT NULL,
  comment_id INTEGER NULL,
  reviewer_id INTEGER NULL,
  pull_request_edit_session_id INTEGER NULL,
  action VARCHAR NOT NULL,
  fix_request_token VARCHAR(255) NULL,
  old_pull_request_title VARCHAR NULL,
  new_pull_request_title VARCHAR NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (pull_request_id) REFERENCES pull_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES pull_request_reviewers(id) ON DELETE CASCADE,
  FOREIGN KEY (pull_request_edit_session_id) REFERENCES pull_request_edit_sessions(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS comments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pull_request_id INTEGER NOT NULL,
  user_id INTEGER NULL,
  content TEXT NOT NULL,
  is_resolved TINYINT(1) NOT NULL DEFAULT 0,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (pull_request_id) REFERENCES pull_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS document_categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL,
  sidebar_label TEXT NOT NULL,
  position INTEGER NOT NULL DEFAULT 1,
  description TEXT NULL,
  status TEXT CHECK (status IN ('draft','pushed','merged','fix-request')) NOT NULL DEFAULT 'draft',
  parent_id INTEGER NULL,
  user_branch_id INTEGER NULL,
  pull_request_edit_session_id INTEGER NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (pull_request_edit_session_id) REFERENCES pull_request_edit_sessions(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS document_versions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  user_branch_id INTEGER NOT NULL,
  pull_request_edit_session_id INTEGER NULL,
  file_path TEXT NOT NULL,
  status TEXT CHECK (status IN ('draft','pushed','merged','fix-request')) NOT NULL DEFAULT 'draft',
  content TEXT NULL,
  original_blob_sha TEXT NULL,
  slug TEXT NULL,
  category_id INTEGER NULL,
  sidebar_label TEXT NULL,
  file_order INTEGER NOT NULL DEFAULT 1,
  last_edited_by TEXT NULL,
  last_reviewed_by TEXT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  deleted_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (user_branch_id) REFERENCES user_branches(id) ON DELETE CASCADE,
  FOREIGN KEY (pull_request_edit_session_id) REFERENCES pull_request_edit_sessions(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS edit_start_versions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_branch_id INTEGER NOT NULL,
  target_type TEXT NOT NULL CHECK (target_type IN ('document','category')),
  original_version_id INTEGER NULL,
  current_version_id INTEGER NOT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (user_branch_id) REFERENCES user_branches(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS fix_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  token TEXT NULL,
  document_version_id INTEGER NULL,
  document_category_id INTEGER NULL,
  base_document_version_id INTEGER NULL,
  base_category_version_id INTEGER NULL,
  user_id INTEGER NOT NULL,
  pull_request_id INTEGER NOT NULL,
  status TEXT CHECK (status IN ('pending','applied','archived')) NOT NULL DEFAULT 'pending',
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (document_version_id) REFERENCES document_versions(id) ON DELETE CASCADE,
  FOREIGN KEY (document_category_id) REFERENCES document_categories(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (pull_request_id) REFERENCES pull_requests(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS "migrations" ("id" integer primary key autoincrement not null, "migration" varchar not null, "batch" integer not null);
CREATE TABLE IF NOT EXISTS personal_access_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tokenable_type TEXT NOT NULL,
  tokenable_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  token TEXT NOT NULL UNIQUE,
  abilities TEXT NULL,
  last_used_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL
);
CREATE TABLE IF NOT EXISTS pull_request_edit_session_diffs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pull_request_edit_session_id INTEGER NOT NULL,
  target_type TEXT NOT NULL CHECK (target_type IN ('document','category')),
  diff_type TEXT CHECK (diff_type IN ('created','updated','deleted')),
  original_version_id INTEGER NULL,
  current_version_id INTEGER NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (pull_request_edit_session_id) REFERENCES pull_request_edit_sessions(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS pull_request_edit_sessions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pull_request_id INTEGER NOT NULL,
  user_id INTEGER NULL,
  token TEXT NOT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (pull_request_id) REFERENCES pull_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS pull_request_reviewers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pull_request_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  action_status TEXT CHECK (action_status IN ('pending','fix_requested','approved')) NOT NULL DEFAULT 'pending',
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (pull_request_id) REFERENCES pull_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS pull_requests (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_branch_id INTEGER NOT NULL,
  title VARCHAR NOT NULL,
  description TEXT NULL,
  github_url VARCHAR NULL,
  pr_number INTEGER NULL,
  status VARCHAR CHECK (status IN ('opened','merged','closed','conflict')) NOT NULL DEFAULT 'opened',
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (user_branch_id) REFERENCES user_branches(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS sessions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
  sess TEXT NOT NULL,
  expired_at DATETIME NOT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  updated_at DATETIME NULL
);
CREATE TABLE IF NOT EXISTS user_branches (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  branch_name TEXT NOT NULL,
  snapshot_commit TEXT NOT NULL,
  is_active INTEGER DEFAULT 1,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  password TEXT NOT NULL,
  role TEXT CHECK (role IN ('owner','admin','editor')) NOT NULL DEFAULT 'editor',
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  deleted_at DATETIME NULL
);
CREATE INDEX idx_activity_log_pull_request_id_created_at ON activity_log_on_pull_requests(pull_request_id, created_at);
CREATE INDEX idx_activity_log_user_id_action ON activity_log_on_pull_requests(user_id, action);
CREATE INDEX idx_document_categories_pr_edit_session ON document_categories(pull_request_edit_session_id);
CREATE INDEX idx_document_versions_branch ON document_versions(user_branch_id);
CREATE INDEX idx_document_versions_file_path ON document_versions(file_path);
CREATE INDEX idx_document_versions_pr_edit_session ON document_versions(pull_request_edit_session_id);
CREATE INDEX idx_document_versions_status ON document_versions(status);
CREATE INDEX idx_document_versions_user ON document_versions(user_id);
CREATE INDEX idx_edit_start_versions_current_target ON edit_start_versions(current_version_id, target_type);
CREATE INDEX idx_edit_start_versions_original_target ON edit_start_versions(original_version_id, target_type);
CREATE INDEX idx_fix_requests_doc_or_cat ON fix_requests(document_version_id, document_category_id);
CREATE INDEX idx_pull_request_edit_sessions_pull_request_id ON pull_request_edit_sessions(pull_request_id);
CREATE INDEX idx_pull_request_edit_sessions_user_id ON pull_request_edit_sessions(user_id);
CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON personal_access_tokens(tokenable_type, tokenable_id);
CREATE UNIQUE INDEX pull_request_edit_sessions_token_unique ON pull_request_edit_sessions(token);
CREATE UNIQUE INDEX pull_request_reviewers_pull_request_id_user_id_unique ON pull_request_reviewers(pull_request_id, user_id);
COMMIT;
