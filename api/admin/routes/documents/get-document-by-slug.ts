import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { sessionService } from '../../../../src/services/sessionService';
import { db } from '@site/src/lib/db';

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

// 特定のslugのドキュメントを取得するAPI
router.get('/slug', async (req: Request, res: Response) => {
  try {
    const sessionId = req.cookies.sid;

    // 認証チェック
    const loginUser = await sessionService.getSessionUser(sessionId);
    if (!loginUser) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.NO_SESSION,
      });
    }

    // パスからslugとcategoryPathを取得
    const pathParts = req.query.slug.toString().split('/');
    const slug = pathParts[pathParts.length - 1];
    const categoryPath = pathParts.slice(0, -1);

    // カテゴリ情報を取得
    let categoryResult;
    if (categoryPath.length === 0) {
      // カテゴリが指定されていない場合（ルートカテゴリ）
      categoryResult = await db.execute({
        sql: 'SELECT id FROM document_categories WHERE parent_id IS NULL AND is_deleted = 0 LIMIT 1',
      });
    } else {
      // カテゴリが指定されている場合
      categoryResult = await db.execute({
        sql: `
          WITH RECURSIVE category_tree AS (
            -- ルートカテゴリを取得
            SELECT id, slug, parent_id, sidebar_label
            FROM document_categories
            WHERE slug = ? AND is_deleted = 0
            UNION ALL
            -- 子カテゴリを再帰的に取得
            SELECT c.id, c.slug, c.parent_id, c.sidebar_label
            FROM document_categories c
            INNER JOIN category_tree ct ON c.parent_id = ct.id
            WHERE c.is_deleted = 0
          )
          SELECT id FROM category_tree
          WHERE slug = ?
        `,
        args: [categoryPath[0], categoryPath[categoryPath.length - 1] || categoryPath[0]],
      });
    }

    const category = categoryResult.rows[0];
    if (!category) {
      return res.status(HTTP_STATUS.NOT_FOUND).json({
        error: 'カテゴリが見つかりません',
      });
    }

    // ドキュメントバージョンを取得
    const documentResult = await db.execute({
      sql: `
        SELECT 
          slug, sidebar_label, content, file_order, is_public, 
          last_edited_by
        FROM document_versions 
        WHERE slug = ? 
          AND category_id = ? 
          AND is_deleted = 0
        LIMIT 1
      `,
      args: [slug, category.id],
    });

    const documentVersion = documentResult.rows[0];
    if (!documentVersion) {
      return res.status(HTTP_STATUS.NOT_FOUND).json({
        error: 'ドキュメントが見つかりません',
      });
    }

    // レスポンスデータを構築
    const response = {
      slug: documentVersion.slug,
      label: documentVersion.sidebar_label,
      content: documentVersion.content,
      fileOrder: documentVersion.file_order,
      isPublic: documentVersion.is_public === 1,
      lastEditedBy: documentVersion.last_edited_by,
      source: 'database' as const,
    };

    return res.json(response);
  } catch (error) {
    console.error('ドキュメント取得エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const getDocumentBySlugRouter = router;
