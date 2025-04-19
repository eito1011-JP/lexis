import { db } from "@site/src/lib/db";
import { GITHUB_TOKEN, GITHUB_REPO, GITHUB_OWNER } from "../config";

const initOctokit = async () => {
  const { Octokit } = await import('@octokit/rest');
  return new Octokit({
    auth: GITHUB_TOKEN
  });
};

/**
 * @returns {Promise<boolean>} 未コミットの変更がある場合はtrue
 */
export async function checkUserDraft(userId: number): Promise<boolean> {  
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

export async function createBranch(userId: number, email: string): Promise<void> {
  const snapshotCommit = await findLatestCommit();

  const timestamp = new Date()
  .toISOString()
  .slice(0, 10)
  .replace(/-/g, '');

  const branchName = `feature/${email}_${timestamp}`;

  // user branchの状態を記録
  await db.execute({
    sql: 'INSERT INTO user_branches (user_id, branch_name, snapshot_commit, is_active, pr_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
    args: [userId, branchName, snapshotCommit, 1, 'none', timestamp, timestamp],
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
