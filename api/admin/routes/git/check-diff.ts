import express from 'express';
import { exec } from 'child_process';
import { promisify } from 'util';
import { sessionService } from '../../../../src/services/sessionService';

const execAsync = promisify(exec);
const router = express.Router();

/**
 * 現在のブランチに変更があるかどうかを確認するエンドポイント
 */
router.get('/check-diff', async (req, res) => {
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

    try {
      // Gitリポジトリの状態を確認
      const { stdout } = await execAsync('git status --porcelain');
      const hasDiff = stdout.trim().length > 0;

      return res.json({ hasDiff });
    } catch (gitError) {
      console.error('Git状態確認エラー:', gitError);
      // Gitコマンドが失敗した場合は、ダミーレスポンスを返す
      return res.json({ hasDiff: false });
    }
  } catch (error) {
    console.error('Check-diff APIエラー:', error);
    return res.status(500).json({ error: '内部サーバーエラー' });
  }
});

export const checkDiffRouter = router;
