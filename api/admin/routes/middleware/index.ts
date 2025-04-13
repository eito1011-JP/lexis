// api/admin/routes/middleware/index.ts
import { Request, Response, NextFunction } from 'express';

// リクエストロギングミドルウェア
export const requestLogger = (req: Request, res: Response, next: NextFunction) => {
  console.log(`[${new Date().toISOString()}] ${req.method} ${req.url}`);
  next();
};

// エラーハンドリングミドルウェア
export const errorHandler = (err: Error, req: Request, res: Response, next: NextFunction) => {
  console.error('エラー発生:', err);
  res.status(500).json({
    error: '内部サーバーエラーが発生しました',
    message: process.env.NODE_ENV === 'development' ? err.message : undefined,
  });
};

// すべてのミドルウェアをまとめたもの
export const middleware = [requestLogger, errorHandler];
