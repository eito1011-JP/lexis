import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { sessionService } from '../../../../src/services/sessionService';
import fs from 'fs';
import path from 'path';

// Request型の拡張
declare global {
  namespace Express {
    interface Request {
      user?: {
        userId: number;
        email: string;
      };
    }
  }
}

const router = Router();

router.get('/folders', async (req: Request, res: Response) => {
  try {
    const sessionId = req.cookies.sid;

    const skipAuthCheck = process.env.NODE_ENV !== 'production';

    if (!sessionId && !skipAuthCheck) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.NO_SESSION,
      });
    }

    let user = null;
    if (sessionId) {
      user = await sessionService.getSessionUser(sessionId);
    }

    if (!user && !skipAuthCheck) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.INVALID_SESSION,
      });
    }

    const docsDir = path.join(process.cwd(), 'docs');

    // docs ディレクトリが存在しない場合は作成
    if (!fs.existsSync(docsDir)) {
      fs.mkdirSync(docsDir, { recursive: true });
    }

    // docs 配下のフォルダを取得
    const items = fs.readdirSync(docsDir, { withFileTypes: true });
    const folders = items.filter(item => item.isDirectory()).map(item => item.name);

    return res.status(HTTP_STATUS.OK).json({
      folders,
    });
  } catch (error) {
    console.error('フォルダ一覧取得エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const getFoldersRouter = router;
