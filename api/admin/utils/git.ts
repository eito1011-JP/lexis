import { db } from "@site/src/lib/db";
import { getAuthenticatedUser } from "./auth";
/**
 * ユーザーの作業ディレクトリに未コミットの変更があるかチェックする
 * @returns {Promise<boolean>} 未コミットの変更がある場合はtrue
 */
  export async function checkUserDraft(sessionId: string): Promise<boolean> {  
  try {
    const loginUser = await getAuthenticatedUser(sessionId);
    const hasDraft = await db.execute({
      sql: 'SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END as has_draft FROM document_versions WHERE user_id = ? AND status = ?',
          args: [loginUser.userId, 'draft'],
    });

    if (!hasDraft.rows[0].has_draft) {
      return false;
    } else {
      return true;
    }

  } catch (error) {
    console.error('error');
    throw new Error('diffの確認に失敗しました');
  }
} 

export async function createBranch(userId: string): Promise<void> {
  // github api呼び出してmainブランチの最新コミットを取得
  const snapshotCommit = await findLatestCommit(userId);

  const timestamp = Math.floor(Date.now() / 1000);
  const branchName = `feature/${userId}_${timestamp}`;

  // user branchの状態を記録
  await db.execute({
    sql: 'INSERT INTO user_branch (user_id, branch_name, snapshot_commit, is_active, pr_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
    args: [userId, branchName, snapshotCommit, 1, 'none', timestamp, timestamp],
  });

  // userの編集documentの状態を記録
  await db.execute({
    sql: 'INSERT INTO document_versions (user_id, branch_id, file_path, status, content, original_blob_sha, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    args: [userId, branchName, 'draft'],
  });
}

async function findLatestCommit(userId: string): Promise<string> {
  const snapshotCommit = await db.execute({
    sql: 'SELECT snapshot_commit FROM user_branch WHERE user_id = ?',
    args: [userId],
  });
}