import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { db } from '@site/src/lib/db';
import { getAuthenticatedUser } from '../../utils/auth';
import { initBranchSnapshot } from '../../utils/git';
import { updateDocumentFileOrders } from '../../../utils/update-file-order';

// 型定義
interface UpdateDocumentRequest {
  category: string;
  label: string;
  content: string;
  isPublic: boolean;
  slug: string;
  fileOrder: string | number;
  id: number;
  email: string;
}

interface DocumentVersion {
  id: number;
  file_path: string;
  content: string;
  slug: string;
  sidebar_label: string;
  file_order: number;
  is_public: boolean;
  category_id: number;
}

// バリデーション関数
const validateUpdateRequest = (data: Partial<UpdateDocumentRequest>): string | null => {
  if (!data.label || !data.content || !data.slug) {
    return 'タイトル、本文、slugは必須です';
  }

  if (!/^[a-z0-9-]+$/.test(data.slug)) {
    return 'slugは英小文字、数字、ハイフンのみ使用できます';
  }

  if (
    data.fileOrder != null && // null/undefined チェックのみ
    (!Number.isInteger(Number(data.fileOrder)) ||
      !isFinite(Number(data.fileOrder)) ||
      Number(data.fileOrder) < 1)
  ) {
    return '表示順序は1以上の有効な整数で入力してください';
  }

  return null;
};

// データベース操作関数
const dbOperations = {
  getExistingDocument: async (id: number): Promise<DocumentVersion | null> => {
    const result = await db.execute({
      sql: 'SELECT * FROM document_versions WHERE id = ? AND is_deleted = 0 LIMIT 1',
      args: [id],
    });
    const row = result.rows[0];
    if (!row) return null;

    return {
      id: Number(row.id),
      file_path: String(row.file_path),
      content: String(row.content),
      slug: String(row.slug),
      sidebar_label: String(row.sidebar_label),
      file_order: Number(row.file_order),
      is_public: Boolean(row.is_public),
      category_id: Number(row.category_id),
    };
  },

  getActiveBranch: async (userId: number): Promise<number | null> => {
    const result = await db.execute({
      sql: 'SELECT id FROM user_branches WHERE user_id = ? AND is_active = ? AND pr_status = ?',
      args: [userId, 1, 'none'],
    });
    return result.rows[0]?.id ? Number(result.rows[0].id) : null;
  },

  getMaxFileOrder: async (categoryId: number): Promise<number> => {
    const result = await db.execute({
      sql: 'SELECT MAX(file_order) as max_order FROM document_versions WHERE category_id = ? AND status = ? AND is_deleted = ?',
      args: [categoryId, 'merged', 0],
    });
    return (Number(result.rows[0]?.max_order) || 0) + 1;
  },

  // 新しい位置から元の位置までの範囲のドキュメントを取得
  getDocumentsToShift: async (
    categoryId: number,
    newFileOrder: number,
    oldFileOrder: number,
    userBranchId: number,
    excludeId: number
  ) => {
    let sql: string;
    let args: any[];

    if (newFileOrder < oldFileOrder) {
      // 上に移動する場合：新しい位置以上、元の位置未満の範囲のレコードを+1
      // 例: 3→1の場合、file_order が 1,2 のレコードを 2,3 に移動
      sql = `SELECT * FROM document_versions
             WHERE category_id = ? AND file_order >= ? AND file_order < ? AND is_deleted = 0
             AND (status = 'merged' OR user_branch_id = ?) AND id != ?
             ORDER BY file_order ASC`;
      args = [categoryId, newFileOrder, oldFileOrder, userBranchId, excludeId];
    } else {
      // 下に移動する場合：元の位置超過、新しい位置以下の範囲のレコードを-1
      // 例: 1→3の場合、file_order が 2,3 のレコードを 1,2 に移動
      sql = `SELECT * FROM document_versions
             WHERE category_id = ? AND file_order > ? AND file_order <= ? AND is_deleted = 0
             AND (status = 'merged' OR user_branch_id = ?) AND id != ?
             ORDER BY file_order ASC`;
      args = [categoryId, oldFileOrder, newFileOrder, userBranchId, excludeId];
    }

    return await db.execute({ sql, args });
  },

  createNewDocumentVersion: async (
    userId: number,
    userBranchId: number,
    existingDoc: DocumentVersion,
    updateData: UpdateDocumentRequest,
    finalFileOrder: number,
    categoryId: number,
    loginUserEmail: string
  ) => {
    const now = new Date().toISOString();
    await db.execute({
      sql: `INSERT INTO document_versions (
        user_id, user_branch_id, file_path, status, content, slug,
        sidebar_label, file_order, last_edited_by, created_at, updated_at,
        is_deleted, is_public, category_id
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      args: [
        Number(userId),
        Number(userBranchId),
        String(existingDoc.file_path),
        'draft',
        String(updateData.content),
        String(updateData.slug),
        String(updateData.label),
        Number(finalFileOrder),
        loginUserEmail,
        now,
        now,
        0,
        updateData.isPublic ? 1 : 0,
        Number(categoryId),
      ],
    });
  },
};

const router = Router();

router.put('/', async (req: Request, res: Response) => {
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

    // 2. リクエストデータの取得とバリデーション
    const updateData = req.body as UpdateDocumentRequest;
    const validationError = validateUpdateRequest(updateData);
    if (validationError) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({ error: validationError });
    }

    // 3. 既存ドキュメントの取得
    const existingDoc = await dbOperations.getExistingDocument(updateData.id);
    if (!existingDoc) {
      return res.status(HTTP_STATUS.NOT_FOUND).json({
        error: '編集対象のドキュメントが見つかりません',
      });
    }

    // 4. アクティブブランチの取得または作成
    let userBranchId = await dbOperations.getActiveBranch(loginUser.userId);
    if (!userBranchId) {
      await initBranchSnapshot(loginUser.userId, loginUser.email);
      userBranchId = await dbOperations.getActiveBranch(loginUser.userId);
      if (!userBranchId) {
        throw new Error('ブランチの作成に失敗しました');
      }
    }

    try {
      // 5. file_orderの処理
      const categoryId = existingDoc.category_id;
      let finalFileOrder = updateData.fileOrder;

      if (updateData.fileOrder === '' || updateData.fileOrder === null || updateData.fileOrder === undefined) {
        finalFileOrder = await dbOperations.getMaxFileOrder(categoryId);
      } else {
        const newFileOrder = Number(updateData.fileOrder);
        const oldFileOrder = existingDoc.file_order;

        // file_orderが変更された場合のみ他のドキュメントを調整
        if (newFileOrder !== oldFileOrder) {
          const documentsToShift = await dbOperations.getDocumentsToShift(
            categoryId,
            newFileOrder,
            oldFileOrder,
            userBranchId,
            updateData.id
          );

          if (documentsToShift.rows.length > 0) {
            const isMovingUp = newFileOrder < oldFileOrder;
            const documentsToUpdate = documentsToShift.rows.map(row => ({
              id: Number(row.id),
              file_order: Number(row.file_order),
              file_path: String(row.file_path),
              content: String(row.content),
              slug: String(row.slug),
              sidebar_label: String(row.sidebar_label),
              is_public: Boolean(row.is_public),
              category_id: Number(row.category_id),
            }));
            await updateDocumentFileOrders(
              documentsToUpdate,
              loginUser.userId,
              userBranchId,
              loginUser.email,
              isMovingUp
            );
          }
        }
      }

      // 6. 既存ドキュメントを論理削除
      await db.execute({
        sql: `UPDATE document_versions SET is_deleted = 1 WHERE id = ? AND is_deleted = 0`,
        args: [updateData.id],
      });

      // 7. 新しいドキュメントバージョンの作成
      await dbOperations.createNewDocumentVersion(
        loginUser.userId,
        userBranchId,
        existingDoc,
        updateData,
        Number(finalFileOrder),
        Number(categoryId),
        loginUser.email
      );

      // 8. 成功レスポンス
      return res.status(HTTP_STATUS.OK).json({
        success: true,
        message: 'ドキュメントが更新されました',
        documentId: updateData.slug,
      });
    } catch (error) {
      throw error;
    }
  } catch (error) {
    console.error('ドキュメント更新エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const updateDocumentRouter = router;
