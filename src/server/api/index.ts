import { Router } from 'express';
import userRouter from './routes/user';

const router = Router();

// ユーザー用APIルートの設定
router.use('/user', userRouter);

export default router;
