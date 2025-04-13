import { Router, Request, Response } from 'express';
import { hash } from 'bcrypt';
import { v4 as uuidv4 } from 'uuid';
import { API_ERRORS, SUCCESS_MESSAGES, HTTP_STATUS } from '../../const/errors';
import { userService } from '../../../src/services/userService';
import { sessionService } from '../../../src/services/sessionService';

const router = Router();

router.post('/signup', async (req: Request, res: Response) => {
  try {
    const { email, password } = req.body;

    // 基本的なバリデーション
    if (!email || !password) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({ 
        error: API_ERRORS.AUTH.MISSING_CREDENTIALS 
      });
    }

    // メールアドレスのフォーマットチェック
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({ 
        error: API_ERRORS.AUTH.INVALID_EMAIL_FORMAT 
      });
    }

    // パスワードの強度チェック
    if (password.length < 8) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({ 
        error: API_ERRORS.VALIDATION.PASSWORD_TOO_SHORT 
      });
    };

    // 既存ユーザーの確認 - データベースから
    const userExists = await userService.checkUserExists(email);
    console.log('ユーザー存在確認:', userExists);
    if (userExists) {
      return res.status(HTTP_STATUS.CONFLICT).json({ 
        error: API_ERRORS.AUTH.EMAIL_EXISTS 
      });
    }

    // パスワードのハッシュ化
    const saltRounds = 10;
    const hashedPassword = await hash(password, saltRounds);
    
    // ユーザーIDを生成
    const userId = uuidv4();

    // データベースにユーザーを保存
    const userWithoutPassword = await userService.createUser(email, hashedPassword, userId);
    
    // セッションを作成してセッションIDを取得
    const sessionId = await sessionService.createSession(userId, email);
    
    // クッキーにセッションIDを設定
    res.cookie('sid', sessionId, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      maxAge: 90 * 60 * 60 * 1000 // 90日
    });

    return res.status(HTTP_STATUS.CREATED).json({ 
      success: true, 
      message: SUCCESS_MESSAGES.AUTH.SIGNUP_SUCCESS,
      user: userWithoutPassword,
      isAuthenticated: true
    });
  } catch (error) {
    console.error('Signup error:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ 
      error: API_ERRORS.SERVER.INTERNAL_ERROR 
    });
  }
});

export const signupRouter = router;
