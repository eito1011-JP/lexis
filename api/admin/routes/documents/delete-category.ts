import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { db } from '@site/src/lib/db';
import { getAuthenticatedUser } from '../../utils/auth';
import { getCategoryTreeFromSlug } from '@site/src/utils/category';
import { initBranchSnapshot } from '../../utils/git';

// 型定義
interface DeleteCategoryRequest {
  slug: string;
}

const router = Router();

router.delete('/delete-category', async (req: Request, res: Response) => {
  try {
    // 1. 認証チェック
    const sessionId = req.cookies.sid;
    if (!sessionId) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.NO_SESSION,
      });
    }

    const loginUser = await getAuthenticatedUser(sessionId);
    if (!loginUser) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.INVALID_SESSION,
      });
    }

    // 2. リクエストデータの取得
    const { slug } = req.body as DeleteCategoryRequest;

    if (!slug) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'slugは必須です',
      });
    }

    // 3. ユーザーのアクティブブランチ確認
    const activeBranch = await db.execute({
      sql: 'SELECT id FROM user_branches WHERE user_id = ? AND is_active = ? AND pr_status = ?',
      args: [loginUser.userId, 1, 'none'],
    });

    let userBranchId;

    if (activeBranch.rows.length > 0) {
      // 既存のアクティブブランチを使用
      userBranchId = Number(activeBranch.rows[0].id);
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

    // 4. カテゴリツリーを取得
    const { categories, documents } = await getCategoryTreeFromSlug(slug, userBranchId);

    if (categories.length === 0) {
      return res.status(HTTP_STATUS.NOT_FOUND).json({
        error: 'カテゴリが見つかりません',
      });
    }

    // 5. トランザクション処理
    const now = new Date().toISOString();

    // document_categoriesをis_deleted=1に更新
    for (const category of categories) {
      await db.execute({
        sql: 'UPDATE document_categories SET is_deleted = 1, updated_at = ? WHERE id = ?',
        args: [now, Number(category.id)],
      });
    }

    // document_versionsをis_deleted=1に更新
    if (documents.length > 0) {
      const versionIds = documents.map(doc => Number(doc.id));
      for (const versionId of versionIds) {
        await db.execute({
          sql: 'UPDATE document_versions SET is_deleted = 1, updated_at = ? WHERE id = ?',
          args: [now, versionId],
        });
      }
    }

    // 6. ブランチ管理のために削除したcategoriesを追加
    for (const category of categories) {
      await db.execute({
        sql: `INSERT INTO document_categories (
          sidebar_label, slug, parent_id, user_branch_id, is_deleted, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?)`,
        args: [
          String(category.sidebar_label),
          String(category.slug),
          category.parent_id ? Number(category.parent_id) : null,
          userBranchId,
          1,
          now,
          now
        ],
      });
    }

    // 7. ブランチ管理のために削除したdocument_versionsを追加
    if (documents.length > 0) {
      for (const doc of documents) {
        await db.execute({
          sql: `INSERT INTO document_versions (
            user_id, user_branch_id, document_id, file_path, status, content, slug,
            sidebar_label, file_order, last_edited_by, created_at, updated_at,
            is_deleted, is_public, category_id
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
          args: [
            loginUser.userId,
            userBranchId,
            doc.document_id ? Number(doc.document_id) : null,
            doc.file_path ? String(doc.file_path) : null,
            doc.status ? String(doc.status) : null,
            doc.content ? String(doc.content) : null,
            doc.slug ? String(doc.slug) : null,
            doc.sidebar_label ? String(doc.sidebar_label) : null,
            doc.file_order ? Number(doc.file_order) : null,
            String(loginUser.email),
            now,
            now,
            1,
            doc.is_public ? Number(doc.is_public) : 0,
            doc.category_id ? Number(doc.category_id) : null,
          ],
        });
      }
    }

    // 8. 成功レスポンス
    return res.status(HTTP_STATUS.OK).json({
      success: true,
      message: 'カテゴリとその内容が削除されました',
      deletedCategories: categories.length,
      deletedDocuments: documents.length,
    });

  } catch (error) {
    console.error('カテゴリ削除エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
      details: error instanceof Error ? error.message : 'Unknown error'
    });
  }
});

export const deleteCategoryRouter = router; 