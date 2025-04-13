import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../const/errors';
import { sessionService } from '../../../src/services/sessionService';
import { userService } from '../../../src/services/userService';

const router = Router();

router.get('/', async (req: Request, res: Response) => {
  try {
    const sessionId = req.cookies.sid;

    const skipAuthCheck = process.env.NODE_ENV !== 'production';

    if (!sessionId && !skipAuthCheck) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.NO_SESSION,
      });
    }

    let user = null;
    if (sessionId) {
      user = await sessionService.getSessionUser(sessionId);
      console.log('GET /users - ユーザー:', user ? '認証済み' : '認証されていません');
    }

    if (!user && !skipAuthCheck) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.INVALID_SESSION,
      });
    }

    // 全ユーザーを取得
    const users = await userService.getAllUsers();

    return res.status(HTTP_STATUS.OK).json({
      users,
    });
  } catch (error) {
    console.error('ユーザー一覧取得エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export default router;
