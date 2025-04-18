import { Router, Request, Response } from 'express';
import { db } from '../../../../../src/lib/db';
import { getAuthenticatedUser } from '../../../utils/auth';

const router = Router();

router.get('/check-diff', async (req: Request, res: Response) => {
  try {
    const loginUser = await getAuthenticatedUser(req.cookies.sid);

    const hasUserDraft = await db.execute({
      sql: 'SELECT EXISTS (SELECT 1 FROM document_versions WHERE user_id = ? AND status = ?) as exists',
      args: [loginUser.userId, 'draft'],
    });

    return res.json({ 
      exists: hasUserDraft.rows[0].exists === 1
    });
  } catch (error) {
    console.error('Error checking document versions:', error);
    return res.status(500).json({ error: 'Internal server error' });
  }
});

export default router;

