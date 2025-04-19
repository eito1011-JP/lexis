import { Router, Request, Response } from 'express';
import { db } from '../../../../../src/lib/db';
import { getAuthenticatedUser } from '../../../utils/auth';

const router = Router();

router.get('/check-diff', async (req: Request, res: Response) => {
  try {
    console.log('req.cookies.sid', req.cookies.sid);
    const loginUser = await getAuthenticatedUser(req.cookies.sid);
    
    if (!loginUser) {
      return res.status(401).json({ error: '認証されていません' });
    }

    const hasUserDraft = await db.execute({
      sql: 'SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END as has_draft FROM document_versions WHERE user_id = ? AND status = ?',
      args: [loginUser.userId, 'draft'],
    });

    return res.json({ 
      exists: hasUserDraft.rows[0].has_draft === 1
    });
  } catch (error) {
    console.error('Error checking document versions:', error);
    return res.status(500).json({ error: 'Internal server error' });
  }
});

export default router;

