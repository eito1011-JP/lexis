import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { getAuthenticatedUser } from '../../utils/auth';
import { checkUserDraft } from '../../utils/git';

const router = Router();

router.get('/check-diff', async (req: Request, res: Response) => {
  try {
    const loginUser = await getAuthenticatedUser(req.cookies.sid);
    
    if (!loginUser) {
      return res.status(401).json({ error: '認証されていません' });
    }
    
    const exists = await checkUserDraft(loginUser.userId);
    return res.json({ exists });  
  } catch (error) {
    console.error('diff check error:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR
    });
  }
});

export const checkDiffRouter = router;
export default router; 
