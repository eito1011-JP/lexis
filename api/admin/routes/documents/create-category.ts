import express, { Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { getAuthenticatedUser } from '../../utils/auth';
import { initBranchSnapshot } from '../../utils/git';
import { db } from '@site/src/lib/db';
import { getCategoryIdByPath } from '@site/src/utils/category';

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

interface CreateCategoryRequest {
  slug: string;
  sidebarLabel: string;
  position: number;
  description: string;
  categoryPath: string[];
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
 * body: {
 *   slug: string,
 *   sidebarLabel: string,
 *   position: number,
 *   description: string,
 *   categoryPath: string[]
 * }
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

    const { slug, sidebarLabel, position, description, categoryPath } =
      req.body as CreateCategoryRequest;

    console.log(req.body);
    // validation
    if (!slug || typeof slug !== 'string') {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'slug is required and must be a string',
      });
    }

    if (!sidebarLabel || typeof sidebarLabel !== 'string') {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'sidebarLabel is required and must be a string',
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

    // アクティブなユーザーブランチを確認
    const activeBranch = await db.execute({
      sql: 'SELECT id, branch_name FROM user_branches WHERE user_id = ? AND is_active = ? AND pr_status = ?',
      args: [loginUser.userId, 1, 'none'],
    });

    let userBranchId;
    const now = new Date();

    if (activeBranch.rows.length > 0) {
      userBranchId = activeBranch.rows[0].id;
    } else {
      await initBranchSnapshot(loginUser.userId, loginUser.email);

      const newBranch = await db.execute({
        sql: 'SELECT id FROM user_branches WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
        args: [loginUser.userId],
      });

      if (newBranch.rows.length === 0) {
        throw new Error('ブランチの作成に失敗しました');
      }

      userBranchId = newBranch.rows[0].id;
    }

    const belongedCategroy = await getCategoryIdByPath(categoryPath);

    // // カテゴリの重複チェック
    const existingCategory = await db.execute({
      sql: `SELECT id FROM document_categories 
            WHERE slug = ? AND parent_id IS ?`,
      args: [slug, belongedCategroy],
    });

    if (existingCategory.rows.length > 0) {
      return res.status(HTTP_STATUS.CONFLICT).json({
        error: 'このslugのカテゴリは既に存在します',
      });
    }

    // カテゴリをデータベースに保存
    const categoryResult = await db.execute({
      sql: `INSERT INTO document_categories (
        slug, sidebar_label, position, description, 
        status, last_edited_by, user_branch_id, parent_id,
        created_at, updated_at, is_deleted
      ) VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?) RETURNING id`,
      args: [
        slug,
        sidebarLabel,
        position,
        description,
        loginUser.email,
        userBranchId,
        belongedCategroy,
        now,
        now,
        0,
      ],
    });

    const categoryId = categoryResult.rows[0].id;

    return res.status(HTTP_STATUS.CREATED).json({
      id: categoryId,
      slug,
      label: sidebarLabel,
      position,
      description,
    });
  } catch (error) {
    console.error('カテゴリ作成エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: 'Failed to create category',
    });
  }
});

export const createCategoryRouter = router;
