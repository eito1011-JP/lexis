import { db } from '../lib/db';

export const sessionService = {
  // セッションの作成
  async createSession(userId: number, email: string): Promise<number> {
    const expireAt = new Date();
    expireAt.setDate(expireAt.getDate() + 90); // 90日後に期限切れ

    const sessionData = {
      userId,
      email,
      createdAt: new Date().toISOString(),
    };

    try {
      // 既存のセッションを確認
      const existingSession = await db.execute({
        sql: 'SELECT id FROM sessions WHERE user_id = ?',
        args: [userId],
      });

      if (existingSession.rows.length > 0) {
        // 既存のセッションがある場合は更新
        await db.execute({
          sql: 'UPDATE sessions SET sess = ?, expired_at = ? WHERE user_id = ?',
          args: [JSON.stringify(sessionData), expireAt, userId],
        });
        return existingSession.rows[0].id as number;
      } else {
        // 新規セッションの作成
        const result = await db.execute({
          sql: 'INSERT INTO sessions (user_id, sess, expired_at) VALUES (?, ?, ?)',
          args: [userId, JSON.stringify(sessionData), expireAt],
        });

        return Number(result.lastInsertRowid);
      }
    } catch (error) {
      console.error('セッション作成エラー:', error);
      throw error;
    }
  },

  // セッションの検証
  async getSessionUser(sessionId: string) {
    try {
      const result = await db.execute({
        sql: 'SELECT user_id, sess FROM sessions WHERE user_id = ? AND expired_at > ?',
        args: [sessionId, new Date()],
      });

      if (result.rows.length === 0) {
        return null;
      }

      const sessionData = JSON.parse(result.rows[0].sess as string);
      return {
        userId: sessionData.userId,
        email: sessionData.email,
      };
    } catch (error) {
      console.error('セッション検証エラー:', error);
      return null;
    }
  },

  // セッションの削除
  async deleteSession(sessionId: string): Promise<void> {
    try {
      await db.execute({
        sql: 'DELETE FROM sessions WHERE id = ?',
        args: [sessionId],
      });
    } catch (error) {
      console.error('セッション削除エラー:', error);
      throw error;
    }
  },

  // 期限切れセッションのクリーンアップ
  async cleanupSessions(): Promise<void> {
    try {
      await db.execute({
        sql: 'DELETE FROM sessions WHERE expired_at < ?',
        args: [new Date().toISOString()],
      });
    } catch (error) {
      console.error('セッションクリーンアップエラー:', error);
      throw error;
    }
  },
};
