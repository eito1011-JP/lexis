import express from 'express';
import cors from 'cors';
import bodyParser from 'body-parser';
import cookieParser from 'cookie-parser';
import { signupRouter } from './routes/signup';
import { loginRouter } from './routes/login';
import { createCategoryRouter } from './routes/documents/create-category';
import { updateCategoryRouter } from './routes/documents/update-category';
import { getCategoriesRouter } from './routes/documents/get-categories';
import { createDocumentRouter } from './routes/documents/create-document';
import { getCategoryContentsRouter } from './routes/documents/get-category-contents';
import { middleware } from './routes/middleware';
import { sessionService } from '../../src/services/sessionService';
import usersRouter from './routes/users';
import documentGitCheckDiffRouter from './routes/documents/git/check-diff';
import documentGitCreatePrRouter from './routes/documents/git/create-pr';
import documentGitDiffRouter from './routes/documents/git/diff';
import { getDocumentsRouter } from './routes/documents/get-documents';
import { getDocumentBySlugRouter } from './routes/documents/get-document-by-slug';
import { updateDocumentRouter } from './routes/documents/update-document';
import { deleteDocumentRouter } from './routes/documents/delete-document';
import { getCategoryBySlugRouter } from './routes/documents/get-category-by-slug';
import { deleteCategoryRouter } from './routes/documents/delete-category';

// Expressアプリの初期化
const app = express();

// ミドルウェアの設定
app.use(
  cors({
    origin:
      process.env.NODE_ENV === 'production' ? 'https://yourdomain.com' : 'http://localhost:3002',
    credentials: true, // クッキーを含むリクエストを許可
  })
);
app.use(bodyParser.json());
app.use(cookieParser());

// その他のミドルウェアを設定
middleware.forEach(mw => app.use(mw));

// ルートの登録
app.use('/api', signupRouter);
app.use('/api', loginRouter);
app.use('/api/documents', createCategoryRouter);
app.use('/api/documents', updateCategoryRouter);
app.use('/api/documents', getCategoriesRouter);
app.use('/api/documents', createDocumentRouter);
app.use('/api/documents', getCategoryContentsRouter);
app.use('/api/documents', getDocumentsRouter);
app.use('/api/documents', getDocumentBySlugRouter);
app.use('/api/documents', updateDocumentRouter);
app.use('/api/documents', deleteDocumentRouter);
app.use('/api/documents', deleteCategoryRouter);
app.use('/api/users', usersRouter);
app.use('/api/documents/git/check-diff', documentGitCheckDiffRouter);
app.use('/api/documents/git/create-pr', documentGitCreatePrRouter);
app.use('/api/documents/git/diff', documentGitDiffRouter);
app.use('/api/documents', getCategoryBySlugRouter);

// セッション確認 - 既存のエンドポイント
app.get('/api/auth/session', async (req, res) => {
  const sessionId = req.cookies.sid;

  if (!sessionId) {
    return res.json({
      authenticated: false,
      message: 'セッションがありません',
    });
  }

  try {
    const user = await sessionService.getSessionUser(sessionId);

    if (!user) {
      return res.json({
        authenticated: false,
        message: 'セッションが無効または期限切れです',
      });
    }

    return res.json({
      authenticated: true,
      user,
    });
  } catch (error) {
    console.error('セッション確認エラー:', error);
    return res.status(500).json({
      authenticated: false,
      error: 'セッション確認中にエラーが発生しました',
    });
  }
});

// 定期的に期限切れセッションをクリーンアップ
setInterval(
  async () => {
    try {
      await sessionService.cleanupSessions();
      console.log('期限切れセッションをクリーンアップしました');
    } catch (error) {
      console.error('セッションクリーンアップエラー:', error);
    }
  },
  90 * 60 * 60 * 1000
); // 90日ごとに実行

// サーバーの起動
const PORT = process.env.API_PORT || 3001;

function startServer() {
  app.listen(PORT, () => {
    console.log(`APIサーバー実行中: http://localhost:${PORT}`);
  });
}

// エントリーポイント
if (require.main === module) {
  startServer();
}

export { app, startServer };
