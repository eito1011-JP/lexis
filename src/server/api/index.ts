import { Router } from 'express';
import userRouter from './routes/user';

const router = Router();

// 管理者用APIルートの設定
router.use('/admin/user', userRouter);

export default router;
