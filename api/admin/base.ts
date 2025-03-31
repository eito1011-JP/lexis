import express from 'express';
import cors from 'cors';
import bodyParser from 'body-parser';
import { signupRouter } from './routes/signup';
import { middleware } from './routes/middleware';

// Expressアプリの初期化
const app = express();

// ミドルウェアの設定
app.use(cors()); // CORSを有効化
app.use(bodyParser.json()); // JSONリクエストの解析
app.use(middleware); // カスタムミドルウェア

// ルートの登録
app.use('/api/admin', signupRouter);

// サーバーの起動
const PORT = process.env.API_PORT || 3001;

function startServer() {
  app.listen(PORT, () => {
    console.log(`APIサーバー実行中: http://localhost:${PORT}`);
  });
}

// エントリーポイント
if (import.meta.url === `file://${process.argv[1]}`) {
  startServer();
}

export { app, startServer };
