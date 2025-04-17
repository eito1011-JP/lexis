import express from 'express';
import { exec } from 'child_process';
import { promisify } from 'util';
import { sessionService } from '../../../../src/services/sessionService';

const execAsync = promisify(exec);
const router = express.Router();

interface GitOperationsResult {
  success: boolean;
  pr?: {
    number: string;
    url: string;
  };
  partial?: boolean;
  mock?: boolean;
  message?: string;
}

async function handleGitOperations(title: string, description: string): Promise<GitOperationsResult> {
  try {
    // 1. 現在のブランチを取得
    const { stdout: currentBranch } = await execAsync('git rev-parse --abbrev-ref HEAD');
    const branch = currentBranch.trim();

    // 2. 変更をコミット
    await execAsync('git add .');
    await execAsync(`git commit -m "${title || '更新内容の提出'}"`);

    // 3. リモートにプッシュ
    await execAsync(`git push -u origin ${branch}`);

    try {
      // 4. PRを作成
      const prCommand = `gh pr create --title "${title || '更新内容の提出'}" --body "${description || ''}" --base main --head ${branch}`;
      const { stdout: prResult } = await execAsync(prCommand);
      const prUrl = prResult.trim();
      const prNumber = prUrl.split('/').pop();

      // 5. mainブランチに戻る
      await execAsync('git checkout main');

      return {
        success: true,
        pr: {
          number: prNumber,
          url: prUrl,
        },
      };
    } catch (prError) {
      console.error('PR作成エラー:', prError);
      return {
        success: false,
        partial: true,
        message: '変更は保存されましたが、Pull Requestの自動作成に失敗しました',
      };
    }
  } catch (gitError) {
    console.error('Git操作エラー:', gitError);
    return {
      success: false,
      message: 'Git操作中にエラーが発生しました',
    };
  }
}

/**
 * 現在のブランチからPull Requestを作成するエンドポイント
 */
router.post('/create-pr', async (req, res) => {
  // セッションチェック
  const sessionId = req.cookies.sid;
  if (!sessionId) {
    return res.status(401).json({ error: '認証が必要です' });
  }

  try {
    const user = await sessionService.getSessionUser(sessionId);
    if (!user) {
      return res.status(401).json({ error: 'セッションが無効または期限切れです' });
    }

    const { title, description } = req.body;
    const result = await handleGitOperations(title, description);
    return res.json(result);
  } catch (error) {
    console.error('Create-PR APIエラー:', error);
    return res.status(500).json({ error: '内部サーバーエラー' });
  }
});

export const createPRRouter = router;
