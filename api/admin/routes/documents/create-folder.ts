import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import fs from 'fs';
import path from 'path';
import { getAuthenticatedUser } from '../../utils/auth';
import { checkUserDraft, createBranch } from '../../utils/git';

// Request型の拡張
declare global {
  namespace Express {
    interface Request {
      user?: {
        userId: string;
        email: string;
        lastActivity: string;
      };
    }
  }
}

const router = Router();

router.post('/create-folder', async (req: Request, res: Response) => {
  try {
    const sessionId = req.cookies.sid;

    if (!sessionId) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.NO_SESSION,
      });
    }

    // ログインユーザーを取得
    const loginUser = await getAuthenticatedUser(sessionId);

    // check diff
    const hasDraft = await checkUserDraft(loginUser.userId);

    if (!hasDraft) {
      await createBranch(loginUser.userId, loginUser.email);
    } 

    const { folderName } = req.body;

    if (!folderName || typeof folderName !== 'string') {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'フォルダ名が指定されていないか、無効です',
      });
    }

    const docsDir = path.join(process.cwd(), 'docs');

    const newFolderPath = path.join(docsDir, folderName);

    if (fs.existsSync(newFolderPath)) {
      return res.status(HTTP_STATUS.CONFLICT).json({
        error: '同名のフォルダが既に存在します',
      });
    }

    fs.mkdirSync(newFolderPath, { recursive: true });

    // 空のフォルダをGitで追跡するために.gitkeepファイルを作成
    const gitkeepPath = path.join(newFolderPath, '.gitkeep');
    fs.writeFileSync(gitkeepPath, '');

    return res.status(HTTP_STATUS.CREATED).json({
      message: 'フォルダが作成されました',
      folderName,
      path: newFolderPath,
    });
  } catch (error) {
    console.error('フォルダ作成エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const createFolderRouter = router;
