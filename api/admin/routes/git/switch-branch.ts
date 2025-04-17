import express from 'express';
import { exec } from 'child_process';
import { promisify } from 'util';
import { sessionService } from '../../../../src/services/sessionService';

const execAsync = promisify(exec);
const router = express.Router();

/**
 * 指定されたブランチに切り替えるエンドポイント
 */
router.post('/switch-branch', async (req, res) => {
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

    const { branchName } = req.body;

    if (!branchName) {
      return res.status(400).json({ error: 'ブランチ名は必須です' });
    }

    try {
      // 現在のブランチの変更を確認
      const { stdout: statusStdout } = await execAsync('git status --porcelain');
      if (statusStdout.trim().length > 0) {
        return res.status(400).json({ 
          error: '未コミットの変更があります。変更をコミットまたは破棄してからブランチを切り替えてください。' 
        });
      }

      // ブランチを切り替え
      await execAsync(`git checkout ${branchName}`);
      
      return res.json({ 
        success: true,
        message: `ブランチを ${branchName} に切り替えました`
      });
    } catch (gitError) {
      console.error('ブランチ切り替えエラー:', gitError);
      return res.status(500).json({ 
        error: 'ブランチの切り替えに失敗しました',
        details: gitError instanceof Error ? gitError.message : '不明なエラー'
      });
    }
  } catch (error) {
    console.error('Switch branch APIエラー:', error);
    return res.status(500).json({ error: '内部サーバーエラー' });
  }
});

export const switchBranchRouter = router; 