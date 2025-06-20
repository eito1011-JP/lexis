import { Router, Request, Response } from 'express';
import { getAuthenticatedUser } from '../../../utils/auth';
import { db } from '@site/src/lib/db';

const router = Router();

router.get('/', async (req: Request, res: Response) => {
  try {
    const loginUser = await getAuthenticatedUser(req.cookies.sid);

    if (!loginUser) {
      return res.status(401).json({ error: '認証されていません' });
    }

    // アクティブなユーザーブランチを取得
    const activeBranch = await db.execute({
      sql: 'SELECT id FROM user_branches WHERE user_id = ? AND is_active = 1 AND pr_status = ?',
      args: [loginUser.userId, 'none'],
    });

    const hasUserDraft = activeBranch.rows.length > 0;
    const userBranchId = hasUserDraft ? activeBranch.rows[0].id : null;

    return res.json({
      exists: hasUserDraft,
      user_branch_id: userBranchId,
    });
  } catch (error) {
    console.error('Error checking document versions:', error);
    return res.status(500).json({ error: 'Internal server error' });
  }
});

export default router;
