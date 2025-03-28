import type { VercelRequest, VercelResponse } from '@vercel/node';
import { kv } from '@vercel/kv';
import { hash } from 'bcrypt';
import { v4 as uuidv4 } from 'uuid';
import { API_ERRORS, SUCCESS_MESSAGES, HTTP_STATUS } from '../const/errors';

export default async function handler(
  req: VercelRequest,
  res: VercelResponse
) {
  // POSTリクエスト以外は許可しない
  if (req.method !== 'POST') {
    return res.status(HTTP_STATUS.METHOD_NOT_ALLOWED).json({ 
      error: API_ERRORS.AUTH.INVALID_METHOD 
    });
  }

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
    }

    // 既存ユーザーの確認
    const existingUser = await kv.get(`user:${email}`);
    if (existingUser) {
      return res.status(HTTP_STATUS.CONFLICT).json({ 
        error: API_ERRORS.AUTH.EMAIL_EXISTS 
      });
    }

    // パスワードのハッシュ化
    const saltRounds = 10;
    const hashedPassword = await hash(password, saltRounds);
    
    // ユーザーIDを生成
    const userId = uuidv4();

    // ユーザー情報の保存
    const user = {
      id: userId,
      email,
      password: hashedPassword,
      createdAt: new Date().toISOString()
    };

    // メールアドレスとIDの両方からアクセスできるように保存
    await kv.set(`user:${email}`, JSON.stringify(user));
    await kv.set(`userid:${userId}`, email);

    // レスポンスにはパスワードを含めない
    const { password: _, ...userWithoutPassword } = user;
    
    return res.status(HTTP_STATUS.CREATED).json({ 
      success: true, 
      message: SUCCESS_MESSAGES.AUTH.SIGNUP_SUCCESS,
      user: userWithoutPassword
    });
  } catch (error) {
    console.error('Signup error:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ 
      error: API_ERRORS.SERVER.INTERNAL_ERROR 
    });
  }
}
