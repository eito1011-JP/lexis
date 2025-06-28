import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { db } from '@site/src/lib/db';
import { getAuthenticatedUser } from '../../utils/auth';
import { initBranchSnapshot } from '../../utils/git';
import path from 'path';
import fs from 'fs';
import TurndownService from 'turndown';
import { getCategoryIdFromPath } from '@site/api/utils/document-category';

const router = Router();

router.post('/', async (req: Request, res: Response) => {
  try {
    // 1. 認証チェック
    const sessionId = req.cookies.sid;

    if (!sessionId) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.NO_SESSION,
      });
    }

    // ログインユーザーを取得
    const loginUser = await getAuthenticatedUser(sessionId);

    if (!loginUser) {
      return res.status(HTTP_STATUS.UNAUTHORIZED).json({
        error: API_ERRORS.AUTH.INVALID_SESSION,
      });
    }

    const { category, label, content, isPublic, slug, fileOrder } = req.body;

    // categoryのslugから階層を特定
    const belongedCategoryId = await getCategoryIdFromPath(category);

    // 2. バリデーション
    if (!label || !content || !slug) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'タイトル、本文、slugは必須です',
      });
    }

    // slugの形式チェック
    if (!/^[a-z0-9-]+$/.test(slug)) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'slugは英小文字、数字、ハイフンのみ使用できます',
      });
    }

    // file_orderが整数かチェック
    if (fileOrder && !Number.isInteger(Number(fileOrder))) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: '表示順序は整数で入力してください',
      });
    }

    // 3. 同じカテゴリ内でのslug重複チェック
    const existingDocument = await db.execute({
      sql: `SELECT id FROM document_versions WHERE category_id = ? AND slug = ? AND is_deleted = 0`,
      args: [belongedCategoryId, slug],
    });

    if (existingDocument.rows.length > 0) {
      return res.status(HTTP_STATUS.CONFLICT).json({
        error: '同じカテゴリ内に同じslugのドキュメントが既に存在します',
      });
    }

    // 4. HTMLからMarkdownへの変換
    const turndownService = new TurndownService();
    const markdownContent = turndownService.turndown(content);

    // 5. file_orderの重複処理・自動採番
    let correctedFileOrder = fileOrder ? parseInt(fileOrder) : null;
    if (correctedFileOrder) {
      // file_order重複時、既存のfile_order >= 入力値を+1してずらす
      await db.execute({
        sql: `UPDATE document_versions SET file_order = file_order + 1 WHERE category_id = ? AND status = ? AND file_order >= ? AND is_deleted = 0`,
        args: [belongedCategoryId, 'merged', correctedFileOrder],
      });
    } else {
      // file_order未入力時、カテゴリ内最大値+1をセット
      const maxOrderResult = await db.execute({
        sql: `SELECT MAX(file_order) as maxOrder FROM document_versions WHERE category_id = ?`,
        args: [belongedCategoryId],
      });
      const maxOrder = Number(maxOrderResult.rows[0]?.maxOrder) || 0;
      correctedFileOrder = maxOrder + 1;
    }

    // 6. アクティブなuser_branchesを取得
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

    // 7. ファイルパスの生成
    const targetDir = category
      ? path.join(process.cwd(), 'docs', category)
      : path.join(process.cwd(), 'docs');
    const filePath = path.join(targetDir, `${slug}.md`);

    const documentVersionResult = await db.execute({
      sql: 'INSERT INTO document_versions (user_id, user_branch_id, file_path, status, content, slug, category, sidebar_label, file_order, last_edited_by, category_id, created_at, updated_at, is_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      args: [
        loginUser.userId,
        userBranchId,
        filePath,
        'draft',
        markdownContent,
        slug,
        category,
        label,
        correctedFileOrder,
        loginUser.email,
        belongedCategoryId,
        now,
        now,
        isPublic,
      ],
    });

    const documentVersionId = Number(documentVersionResult.lastInsertRowid);

    await db.execute({
      sql: 'INSERT INTO edit_start_versions (user_branch_id, target_type, original_version_id, current_version_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
      args: [userBranchId, 'document', null, documentVersionId, now, now],
    });

    // 8. 成功レスポンスを返す
    return res.status(HTTP_STATUS.OK).json({
      success: true,
      message: 'ドキュメントが作成されました',
      documentId: slug,
    });
  } catch (error) {
    console.error('ドキュメント作成エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const createDocumentRouter = router;
