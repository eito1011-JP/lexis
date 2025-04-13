import express from 'express';
import { exec } from 'child_process';
import { promisify } from 'util';
import { sessionService } from '../../../../src/services/sessionService';
import { branchService } from '../../../../src/services/branchService';

const execAsync = promisify(exec);
const router = express.Router();

/**
 * 現在のブランチ情報を取得するエンドポイント
 */
router.get('/current-branch', async (req, res) => {
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

    // データベースからユーザーのアクティブブランチを取得
    const activeBranch = await branchService.getActiveBranch(user.userId);

    // Gitの実際のブランチも確認（APIとの整合性確保のため）
    try {
      const { stdout: currentBranch } = await execAsync('git branch --show-current');
      const gitBranchName = currentBranch.trim();

      // データベースのブランチとGitのブランチが異なる場合
      if (activeBranch && activeBranch.branchName !== gitBranchName) {
        console.warn(`データベースのブランチ(${activeBranch.branchName})とGitのブランチ(${gitBranchName})が一致していません`);
        
        // Gitブランチを切り替え試行
        try {
          await execAsync(`git checkout ${activeBranch.branchName}`);
          console.log(`ブランチを ${activeBranch.branchName} に切り替えました`);
        } catch (checkoutError) {
          console.error('Git checkout error:', checkoutError);
          // 切り替えに失敗した場合は警告を記録するが、エラーは返さない
        }
      }

      // アクティブブランチがなく、mainでもない場合はmainに戻す
      if (!activeBranch && gitBranchName !== 'main' && gitBranchName !== 'master') {
        try {
          await execAsync('git checkout main');
          console.log('ブランチをmainに切り替えました');
        } catch (mainCheckoutError) {
          console.error('Git main checkout error:', mainCheckoutError);
        }
      }

      return res.json({
        success: true,
        branch: activeBranch,
        gitBranch: gitBranchName,
      });
    } catch (gitError) {
      console.error('Git branch error:', gitError);
      // Gitコマンドが失敗しても、データベースの情報を返す
      return res.json({
        success: true,
        branch: activeBranch,
        gitError: 'Gitブランチ情報の取得に失敗しました',
      });
    }
  } catch (error) {
    console.error('Current-branch APIエラー:', error);
    return res.status(500).json({ error: '内部サーバーエラー' });
  }
});

export const getCurrentBranchRouter = router; 