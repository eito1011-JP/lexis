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
 * リクエストボディで指定されたslugのディレクトリをdocs配下に作成します。
 *
 * リクエスト:
 * POST /api/admin/documents/create-category
 * body: { slug: string, label: string, position: number, description: string }
 *
 * レスポンス:
 * 成功: { slug: string, label: string, position: number, description: string, path: string }
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

    const { slug, label, position, description } = req.body;

    // validation
    if (!slug || typeof slug !== 'string') {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'slug is required and must be a string',
      });
    }

    if (!label || typeof label !== 'string') {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'label is required and must be a string',
      });
    }

    if (position && typeof position !== 'number') {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'position must be a number',
      });
    }

    if (description && typeof description !== 'string') {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'description must be a string',
      });
    }

    // ログインユーザーを取得
    const loginUser = await getAuthenticatedUser(sessionId);

    // ブランチが存在しない場合は作成
    const hasDraft = await checkUserDraft(loginUser.userId);

    if (!hasDraft) {
      await initBranchSnapshot(loginUser.userId, loginUser.email);
    }

    const docsDir = path.join(process.cwd(), 'docs');

    const newCategoryPath = path.join(docsDir, slug);

    if (fs.existsSync(newCategoryPath)) {
      return res.status(HTTP_STATUS.CONFLICT).json({
        error: 'A category with this slug already exists',
      });
    }

    fs.mkdirSync(newCategoryPath, { recursive: true });

    // 空のカテゴリをGitで追跡するために.gitkeepファイルを作成
    const gitkeepPath = path.join(newCategoryPath, '.gitkeep');
    fs.writeFileSync(gitkeepPath, '');

    // _category.jsonを作成
    const categoryJsonPath = path.join(newCategoryPath, '_category.json');
    fs.writeFileSync(categoryJsonPath, JSON.stringify({
      label,
      position,
      link: {
        type: "generated-index",
        description
      }
    }, null, 2));

    return res.status(HTTP_STATUS.CREATED).json({
      slug,
      label,
      position,
      description,
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
