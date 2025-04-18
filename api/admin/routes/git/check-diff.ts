import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { checkUserDraft } from '../../utils/git';

const router = Router();

router.get('/check-diff', async (req: Request, res: Response) => {
  try {
    const sessionId = req.cookies.sid;
    const exists = await checkUserDraft(sessionId);
    return res.json({ exists });  
  } catch (error) {
    console.error('diff check error:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR
    });
  }
});

export const checkDiffRouter = router; 
