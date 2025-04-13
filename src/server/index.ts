import express from 'express';
import cors from 'cors';
import bodyParser from 'body-parser';
import cookieParser from 'cookie-parser';
import apiRouter from './api';

const app = express();
const PORT = process.env.PORT || 3001;

// ミドルウェアの設定
app.use(cors({
  origin: process.env.NODE_ENV === 'production' 
    ? 'https://yourdomain.com' 
    : 'http://localhost:3000',
  credentials: true
}));
app.use(bodyParser.json());
app.use(cookieParser());

// APIルーターの登録
app.use('/api', apiRouter);

// サーバー起動
app.listen(PORT, () => {
  console.log(`サーバーが起動しました http://localhost:${PORT}`);
});

export default app; 