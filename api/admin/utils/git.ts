import { db } from "@site/src/lib/db";
import { GITHUB_TOKEN, GITHUB_OWNER, GITHUB_REPO } from "../config";
/**
 * @returns {Promise<boolean>} 未コミットの変更がある場合はtrue
 */
  export async function checkUserDraft(userId: string): Promise<boolean> {  
  try {
    const hasDraft = await db.execute({
      sql: 'SELECT * FROM user_branch WHERE user_id = ? AND is_active = 1 AND pr_status = ?',
          args: [userId, 'none'],
    });

    if (!hasDraft.rows[0]) {
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
}

async function findLatestCommit(userId: string): Promise<string> {
const latestCommit = await fetch(
    `https://api.github.com/repos/${GITHUB_OWNER}/${GITHUB_REPO}/git/refs/heads/main`,
    {
      headers: {
        Authorization: `Bearer ${GITHUB_TOKEN}`,
        Accept: 'application/vnd.github.v3+json',
      },
    }
  );

  console.log('latestCommit', latestCommit);

  if (!latestCommit.ok) {
    throw new Error(`GitHub API failed: ${latestCommit.status}`);
  }

  const data = await latestCommit.json();
  return data.object.sha;
}