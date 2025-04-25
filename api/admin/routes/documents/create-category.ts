import express, { Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import fs from 'fs';
import path from 'path';
import { getAuthenticatedUser } from '../../utils/auth';
import { checkUserDraft, initBranchSnapshot } from '../../utils/git';
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

const router = express.Router();

/**
 * カテゴリ作成API
 *
 * 新しいカテゴリ（ディレクトリ）を作成します。
 * リクエストボディで指定されたカテゴリ名のディレクトリをdocs配下に作成します。
 *
 * リクエスト:
 * POST /api/admin/documents/create-category
 * body: { categoryName: string }
 *
 * レスポンス:
 * 成功: { categoryName: string, path: string }
 * 失敗: { error: string }
 */
router.post('/create-category', async (req: Request, res: Response) => {
  try {
    const sessionId = req.cookies.sid;

    if (!sessionId) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.NO_SESSION,
      });
    }

    // ログインユーザーを取得
    const loginUser = await getAuthenticatedUser(sessionId);

    // ブランチが存在しない場合は作成
    const hasDraft = await checkUserDraft(loginUser.userId);

    if (!hasDraft) {
      await initBranchSnapshot(loginUser.userId, loginUser.email);
    }

    const { categoryName } = req.body;

    if (!categoryName || typeof categoryName !== 'string') {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'Category name is required and must be a string',
      });
    }

    const docsDir = path.join(process.cwd(), 'docs');

    const newCategoryPath = path.join(docsDir, categoryName);

    if (fs.existsSync(newCategoryPath)) {
      return res.status(HTTP_STATUS.CONFLICT).json({
        error: 'A category with this name already exists',
      });
    }

    fs.mkdirSync(newCategoryPath, { recursive: true });

    // 空のカテゴリをGitで追跡するために.gitkeepファイルを作成
    const gitkeepPath = path.join(newCategoryPath, '.gitkeep');
    fs.writeFileSync(gitkeepPath, '');

    return res.status(HTTP_STATUS.CREATED).json({
      categoryName,
      path: newCategoryPath,
    });
  } catch (error) {
    console.error('カテゴリ作成エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: 'Failed to create category',
    });
  }
});

export const createCategoryRouter = router;
