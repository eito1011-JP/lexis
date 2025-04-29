import { Router, Request, Response } from 'express';
import { HTTP_STATUS, API_ERRORS } from '../../../const/errors';
import { db } from '@site/src/lib/db';
import { getAuthenticatedUser } from '../../utils/auth';
import { checkUserDraft, initBranchSnapshot } from '../../utils/git';
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

    const { label, content, is_public, reviewer_email } = req.body;

    console.log(label, content, is_public, reviewer_email);

    // バリデーション
    if (!label || !content) {
      return res.status(HTTP_STATUS.BAD_REQUEST).json({
        error: 'タイトル、コンテンツは必須です',
      });
    }

    // 2. ファイルパスの重複チェック（任意）
    const existingDoc = await db.execute({
      sql: 'SELECT id FROM document_versions WHERE file_path = ? LIMIT 1',
      args: [label],
    });

    if (existingDoc.rows.length > 0) {
      return res.status(HTTP_STATUS.CONFLICT).json({
        error: '同じパスのドキュメントが既に存在します',
      });
    }

    // 3. アクティブなuser_branchesを取得
    const activeBranch = await db.execute({
      sql: 'SELECT id, branch_name FROM user_branches WHERE user_id = ? AND is_active = 1',
      args: [loginUser.userId],
    });

    let userBranchId;

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

    // 4. document_versionsにドラフトとして保存
    const now = new Date();

    await db.execute({
      sql: `
        INSERT INTO document_versions 
        (user_id, user_branch_id, file_path, status, content, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
      `,
      args: [loginUser.userId, userBranchId, label, 'draft', content, now, now],
    });

    // フォルダが存在しなければ作成
    const folderPath = path.join(process.cwd(), 'docs', path.dirname(label));
    if (!fs.existsSync(folderPath)) {
      fs.mkdirSync(folderPath, { recursive: true });
    }

    // 追加情報をメタデータとして保存
    const metadata = {
      label,
      isPublic: is_public,
      reviewerEmail: reviewer_email || null,
      createdBy: loginUser.email,
      createdAt: now,
      updatedAt: now,
    };

    // メタデータをJSONファイルとして保存
    const metaFilePath = path.join(process.cwd(), 'docs', `${label}.meta.json`);
    fs.writeFileSync(metaFilePath, JSON.stringify(metadata, null, 2));

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
