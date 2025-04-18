import { db } from "@site/src/lib/db";
import { getAuthenticatedUser } from "./auth";
import { Request } from "express";
/**
 * ユーザーの作業ディレクトリに未コミットの変更があるかチェックする
 * @returns {Promise<boolean>} 未コミットの変更がある場合はtrue
 */
  export async function checkUserDraft(sessionId: string): Promise<boolean> {  
  try {
    const loginUser = await getAuthenticatedUser(sessionId);
    const hasDraft = await db.execute({
      sql: 'SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END as has_draft FROM document_versions WHERE user_id = ? AND status = ?',
          args: [loginUser.userId, 'draft'],
    });

    if (!hasDraft.rows[0].has_draft) {
      return false;
    } else {
      return true;
    }

  } catch (error) {
    console.error('error');
    throw new Error('diffの確認に失敗しました');
  }
} 