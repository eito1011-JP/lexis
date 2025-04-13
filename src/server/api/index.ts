import { Router } from 'express';
import gitRouter from './routes/git';
import userRouter from './routes/user';

const router = Router();

// 管理者用APIルートの設定
router.use('/admin/git', gitRouter);
router.use('/admin/user', userRouter);

export default router; 