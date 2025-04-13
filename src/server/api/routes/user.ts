import { Router } from 'express';

const router = Router();

/**
 * 現在のログインユーザー情報を取得するエンドポイント
 * 実際の実装ではセッションから取得するなどの認証が必要
 */
router.get('/current', (req, res) => {
  try {
    // 実際の実装ではセッションやトークンからユーザー情報を取得
    // このサンプルでは仮のユーザー情報を返す
    const userEmail = req.cookies.userEmail || 'user@example.com';

    res.json({
      success: true,
      email: userEmail,
    });
  } catch (error) {
    console.error('User info error:', error);
    res.status(500).json({
      success: false,
      error: 'ユーザー情報の取得に失敗しました',
    });
  }
});

export default router;
