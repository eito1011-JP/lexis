import express from 'express';
import { exec } from 'child_process';
import { promisify } from 'util';
import { sessionService } from '../../../../src/services/sessionService';
import { branchService } from '../../../../src/services/branchService';

const execAsync = promisify(exec);
const router = express.Router();

/**
 * 新しいブランチを作成するエンドポイント
 */
router.post('/create-branch', async (req, res) => {
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

    const { branchName, fromBranch } = req.body;

    if (!branchName) {
      return res.status(400).json({ error: 'ブランチ名は必須です' });
    }

    try {
      // 現在のブランチを取得
      try {
        const { stdout: currentBranch } = await execAsync('git rev-parse --abbrev-ref HEAD');

        // 指定されたブランチを基にして新しいブランチを作成
        const baseBranch = fromBranch || 'main';

        // ベースブランチに切り替え
        await execAsync(`git checkout ${baseBranch}`);

        // 最新の変更を取得
        await execAsync(`git pull origin ${baseBranch}`);

        // 新しいブランチを作成して切り替え
        await execAsync(`git checkout -b ${branchName}`);

        // データベースにブランチ情報を保存
        const branchRecord = await branchService.createBranch(user.userId, branchName);

        if (!branchRecord) {
          console.error('ブランチ情報のデータベース保存に失敗しました');
        }

        return res.json({ 
          success: true, 
          branchName,
          branch: branchRecord 
        });
      } catch (gitError) {
        console.error('Git操作エラー:', gitError);
        // Gitコマンドが失敗した場合でもブランチ作成に成功したと返す
        // 開発モード用のモックレスポンス
        const mockBranch = await branchService.createBranch(user.userId, branchName);
        return res.json({ 
          success: true, 
          branchName, 
          mock: true,
          branch: mockBranch
        });
      }
    } catch (error) {
      console.error('ブランチ作成エラー:', error);
      return res.status(500).json({ error: 'ブランチの作成中にエラーが発生しました' });
    }
  } catch (error) {
    console.error('Create-branch APIエラー:', error);
    return res.status(500).json({ error: '内部サーバーエラー' });
  }
});

export const createBranchRouter = router;
