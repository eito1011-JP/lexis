import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { db } from '@site/src/lib/db';
import { getAuthenticatedUser } from '../../utils/auth';
import { initBranchSnapshot } from '../../utils/git';

// 型定義
interface DeleteDocumentRequest {
  slug: string;
}

const router = Router();

router.delete('/', async (req: Request, res: Response) => {
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
    const { slug } = req.body as DeleteDocumentRequest;

    if (!slug) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'slugは必須です',
      });
    }

    // 3. 削除対象のドキュメントを取得
    const existingDocument = await db.execute({
      sql: 'SELECT * FROM document_versions WHERE slug = ? AND is_deleted = 0 LIMIT 1',
      args: [slug],
    });

    if (existingDocument.rows.length === 0) {
      return res.status(HTTP_STATUS.NOT_FOUND).json({
        error: '削除対象のドキュメントが見つかりません',
      });
    }

    const document = existingDocument.rows[0];

    // 4. ユーザーのアクティブブランチ確認
    const activeBranch = await db.execute({
      sql: 'SELECT id FROM user_branches WHERE user_id = ? AND is_active = ? AND pr_status = ?',
      args: [loginUser.userId, 1, 'none'],
    });

    let userBranchId;

    if (activeBranch.rows.length > 0) {
      // 既存のアクティブブランチを使用
      userBranchId = Number(activeBranch.rows[0].id);
    } else {
      // 新しいブランチを作成
      await initBranchSnapshot(loginUser.userId, loginUser.email);

      const newBranch = await db.execute({
        sql: 'SELECT id FROM user_branches WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
        args: [loginUser.userId],
      });

      if (newBranch.rows.length === 0) {
        throw new Error('ブランチの作成に失敗しました');
      }

      userBranchId = Number(newBranch.rows[0].id);
    }

    // 5. 既存ドキュメントを論理削除（is_deleted = 1に更新）
    await db.execute({
      sql: 'UPDATE document_versions SET is_deleted = 1 WHERE slug = ? AND is_deleted = 0',
      args: [slug],
    });

    // 6. 削除されたドキュメントのレコードを新規挿入（ブランチ管理用）
    const now = new Date().toISOString();
    await db.execute({
      sql: `INSERT INTO document_versions (
        user_id, user_branch_id, file_path, status, content, slug,
        sidebar_label, file_order, last_edited_by, created_at, updated_at,
        is_deleted, is_public, category_id
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      args: [
        loginUser.userId,
        userBranchId,
        String(document.file_path),
        'draft',
        String(document.content),
        String(document.slug),
        String(document.sidebar_label),
        Number(document.file_order),
        loginUser.email,
        now,
        now,
        1, // is_deleted = 1 で削除状態として挿入
        Boolean(document.is_public),
        Number(document.category_id),
      ],
    });

    // 7. 成功レスポンス
    return res.status(HTTP_STATUS.OK).json({
      success: true,
      message: 'ドキュメントが削除されました',
      documentSlug: slug,
    });
  } catch (error) {
    console.error('ドキュメント削除エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const deleteDocumentRouter = router;
