import { db } from "@site/src/lib/db";
import { GITHUB_TOKEN, GITHUB_REPO, GITHUB_OWNER } from "../config";
import { v4 as uuidv4 } from 'uuid';
const initOctokit = async () => {
  const { Octokit } = await import('@octokit/rest');
  return new Octokit({
    auth: GITHUB_TOKEN
  });
};

/**
 * @returns {Promise<boolean>} 未コミットの変更がある場合はtrue
 */
export async function checkUserDraft(userId: string): Promise<boolean> {  
  try {
    const hasDraft = await db.execute({
      sql: 'SELECT * FROM user_branches WHERE user_id = ? AND is_active = 1 AND pr_status = ?',
          args: [userId, 'none'],
    });

    if (!hasDraft.rows[0]) {
      return false;
    } else {
      return true;
    }

  } catch (error) {
    console.error(error);
    throw new Error('diffの確認に失敗しました');
  }
} 

export async function createBranch(userId: string, email: string): Promise<void> {
  const snapshotCommit = await findLatestCommit();

  const timestamp = Math.floor(Date.now() / 1000);
  const branchName = `feature/${email}_${timestamp}`;
  const id = uuidv4();

  // user branchの状態を記録
  await db.execute({
    sql: 'INSERT INTO user_branches (id, user_id, branch_name, snapshot_commit, is_active, pr_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
    args: [id, userId, branchName, snapshotCommit, 1, 'none', timestamp, timestamp],
  });
}

async function findLatestCommit(): Promise<string> {
  try {
    const octokit = await initOctokit();
    const response = await octokit.request('GET /repos/{owner}/{repo}/git/refs/heads/main', {
      owner: GITHUB_OWNER,
      repo: GITHUB_REPO,  
      headers: {
        'X-GitHub-Api-Version': '2022-11-28'
      }
    });

    return response.data.object.sha;
  } catch (error) {
    console.error('GitHub APIエラー:', error);
    throw new Error('最新のコミット取得に失敗しました');
  }
}