import { v4 as uuidv4 } from 'uuid';
import { db } from '@site/src/lib/db';

export const sessionService = {

    // セッションの作成
  async createSession(userId: string, email: string): Promise<string> {
    const sessionId = uuidv4();
    const expireAt = new Date();
    expireAt.setDate(expireAt.getDate() + 90); // 90日後に期限切れ
    
    try {
      await db.execute({
        sql: 'INSERT INTO sessions (id, user_id, expire_at) VALUES (?, ?, ?)',
        args: [sessionId, userId, expireAt.toISOString()]
      });
      
      return sessionId;
    } catch (error) {
      console.error('セッション作成エラー:', error);
      throw error;
    }
  },
  
  // セッションの検証
  async getSessionUser(sessionId: string) {
    try {
      const result = await db.execute({
        sql: 'SELECT user_id FROM sessions WHERE id = ? AND expire_at > ?',
        args: [sessionId, new Date().toISOString()]
      });
      
      if (result.rows.length === 0) {
        return null;
      }
      
      return {
        userId: result.rows[0].user_id,
        email: result.rows[0].email
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
        args: [sessionId]
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
        sql: 'DELETE FROM sessions WHERE expire_at < ?',
        args: [new Date().toISOString()]
      });
    } catch (error) {
      console.error('セッションクリーンアップエラー:', error);
      throw error;
    }
  }
};
