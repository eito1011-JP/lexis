import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { db } from '@site/src/lib/db';
import { getAuthenticatedUser } from '../../utils/auth';
import { initBranchSnapshot } from '../../utils/git';
import path from 'path';
import fs from 'fs';

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

    const { category, label, content, isPublic, reviewerEmail, slug, displayOrder } = req.body;
    // バリデーション
    if (!label || !content || !slug) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'タイトル、本文、slugは必須です',
      });
    }

    // 2. ファイルパスの重複チェック
    const targetDir = category 
      ? path.join(process.cwd(), 'docs', category)
      : path.join(process.cwd(), 'docs');
    const targetFile = path.join(targetDir, `${slug}.md`);

    if (fs.existsSync(targetFile)) {
      return res.status(HTTP_STATUS.CONFLICT).json({
        error: '同じカテゴリ内に同じslugのドキュメントが既に存在します',
      });
    }

    // 3. アクティブなuser_branchesを取得
    const activeBranch = await db.execute({
      sql: 'SELECT id, branch_name FROM user_branches WHERE user_id = ? AND is_active = ? AND pr_status = ?',
      args: [loginUser.userId, 1, 'none'],
    });
    
    let userBranchId;
    const now = new Date();

    if (activeBranch.rows.length > 0) {
      // 3.1 存在する場合
      userBranchId = activeBranch.rows[0].id;
    } else {
      // 3.2 存在しない場合、新しいブランチを作成
      await initBranchSnapshot(loginUser.userId, loginUser.email);

      // 作成したブランチのIDを取得
      const newBranch = await db.execute({
        sql: 'SELECT id FROM user_branches WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
        args: [loginUser.userId],
      });

      if (newBranch.rows.length === 0) {
        throw new Error('ブランチの作成に失敗しました');
      }

      userBranchId = newBranch.rows[0].id;
    }

    await db.execute({
      sql: 'INSERT INTO document_versions (user_id, user_branch_id, file_path, status, content, slug, category, sidebar_label, display_order, last_edited_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      args: [loginUser.userId, userBranchId, targetFile, 'draft', content, slug, category, label, displayOrder, loginUser.email, now, now],
    });

    // 5. 成功レスポンスを返す
    return res.status(HTTP_STATUS.OK).json({
      success: true,
      message: 'ドキュメントが作成されました',
      documentId: label,
    });
  } catch (error) {
    console.error('ドキュメント作成エラー:', error);
    return res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
      error: API_ERRORS.SERVER.INTERNAL_ERROR,
    });
  }
});

export const createDocumentRouter = router;
