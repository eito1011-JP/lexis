import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { sessionService } from '../../../../src/services/sessionService';
import { db } from '@site/src/lib/db';
import { getCategoryIdFromPath } from '@site/api/utils/document-category';

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

/**
 * カテゴリー情報取得API
 *
 * 指定されたslugのカテゴリー情報を取得します。
 * 階層構造を持つカテゴリーの場合、パスを指定して取得できます。
 *
 * リクエスト:
 * GET /api/admin/documents/category-slug?slug=<category-path>
 * 例: ?slug=morohashitest-1/testtest
 *
 * レスポンス:
 * 成功: {
 *   id: number,
 *   slug: string,
 *   sidebarLabel: string,
 *   position: number,
 *   description: string,
 * }
 * 失敗: { error: string }
 */
router.get('/category-slug', async (req: Request, res: Response) => {
  try {
    const sessionId = req.cookies.sid;

    // 認証チェック
    const loginUser = await sessionService.getSessionUser(sessionId);
    if (!loginUser) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.NO_SESSION,
      });
    }

    // クエリパラメータからslugを取得
    const { slug } = req.query;

    if (!slug || typeof slug !== 'string') {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: '有効なslugが必要です',
      });
    }

    // カテゴリー情報を取得
    const belongedCategoryId = await getCategoryIdFromPath(slug);

    const category = await db.execute({
      sql: `SELECT * FROM document_categories WHERE id = ?`,
      args: [belongedCategoryId],
    });

    const response = {
      id: category.rows[0].id,
      slug: category.rows[0].slug,
      sidebarLabel: category.rows[0].sidebar_label,
      position: category.rows[0].position,
      description: category.rows[0].description,
    };

    return res.status(HTTP_STATUS.OK).json(response);
  } catch (error) {
    console.error('カテゴリー情報取得エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const getCategoryBySlugRouter = router;
