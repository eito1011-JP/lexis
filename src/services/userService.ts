import { db } from '../lib/db';
import { User } from '../types/user';

export const userService = {
  async createUser(email: string, hashedPassword: string): Promise<User> {
    try {
      const result = await db.execute({
        sql: 'INSERT INTO users (email, password, created_at) VALUES (?, ?, ?)',
        args: [email, hashedPassword, new Date().toISOString()],
      });

      const row = result.lastInsertRowid;
      return {
        id: Number(row),
        email: email,
        password: hashedPassword,
        createdAt: new Date(),
      };
    } catch (error) {
      console.error('ユーザー作成エラー:', error);
      throw error;
    }
  },

  async getUserByEmail(email: string): Promise<User | null> {
    const result = await db.execute({
      sql: 'SELECT id, email, password, created_at as createdAt FROM users WHERE email = ?',
      args: [email],
    });

    if (result.rows.length === 0) {
      return null;
    }

    const row = result.rows[0];
    return {
      id: row.id as number,
      email: row.email as string,
      password: row.password as string,
      createdAt: new Date(),
    };
  },

  async checkUserExists(email: string): Promise<boolean> {
    const result = await db.execute({
      sql: 'SELECT 1 FROM users WHERE email = ?',
      args: [email],
    });
    return result.rows.length > 0;
  },

  // 全ユーザー取得
  async getAllUsers(): Promise<Omit<User, 'password'>[]> {
    try {
      const result = await db.execute({
        sql: 'SELECT id, email, created_at FROM users ORDER BY created_at DESC',
      });

      return result.rows.map(row => ({
        id: row.id as number,
        email: row.email as string,
        createdAt: new Date(),
      }));
    } catch (error) {
      console.error('ユーザー一覧取得エラー:', error);
      return [];
    }
  },
};
