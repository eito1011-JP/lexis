import { Router, Request, Response } from 'express';
import { getAuthenticatedUser } from '../../../utils/auth';

const router = Router();

router.get('/create-pr', async (req: Request, res: Response) => {
  try {
    const loginUser = await getAuthenticatedUser(req.cookies.sid);

    if (!loginUser) {
      return res.status(401).json({ error: '認証されていません' });
    }

    //
  } catch (error) {
    console.error('Error checking document versions:', error);
    return res.status(500).json({ error: 'Internal server error' });
  }
});

export default router;
