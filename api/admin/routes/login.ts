import { Router, Request, Response } from 'express';
import { verifyPassword } from '../../utils/password';
import { API_ERRORS, SUCCESS_MESSAGES, HTTP_STATUS } from '../../const/errors';
import { userService } from '../../../src/services/userService';
import { sessionService } from '../../../src/services/sessionService';

const router = Router();

router.post('/login', async (req: Request, res: Response) => {
  try {
    const { email, password } = req.body;

    // 基本的なバリデーション
    if (!email || !password) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: API_ERRORS.AUTH.MISSING_CREDENTIALS,
      });
    }

    // ユーザーの存在確認
    const user = await userService.getUserByEmail(email);

    if (!user) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.INVALID_CREDENTIALS,
      });
    }

    // パスワードの検証
    const isValidPassword = await verifyPassword(password, user.password);
    if (!isValidPassword) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.INVALID_CREDENTIALS,
      });
    }

    // セッションを作成してセッションIDを取得
    const sessionId = await sessionService.createSession(user.id, email);

    // クッキーにセッションIDを設定
    res.cookie('sid', sessionId, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      maxAge: 90 * 60 * 60 * 1000, // 90日
    });

    return res.status(HTTP_STATUS.OK).json({
      success: true,
      message: SUCCESS_MESSAGES.AUTH.LOGIN_SUCCESS,
      user: {
        id: user.id,
        email: user.email,
        createdAt: user.createdAt,
      },
      isAuthenticated: true,
    });
  } catch (error) {
    console.error('Login error:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const loginRouter = router;
