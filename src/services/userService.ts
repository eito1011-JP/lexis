import { db } from '../lib/db';
import { User } from '../types/user';

export const userService = {
  async createUser(
    email: string,
    hashedPassword: string,
    userId: string
  ): Promise<Omit<User, 'password'>> {
    console.log('Creating user:', email, hashedPassword, userId);
    console.log('DB URL:', db);

    await db.execute({
      sql: 'INSERT INTO users (id, email, password, created_at) VALUES (?, ?, ?, ?)',
      args: [userId, email, hashedPassword, new Date().toISOString()],
    });

    return {
      id: userId,
      email,
      createdAt: new Date().toISOString(),
    };
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
      id: row.id as string,
      email: row.email as string,
      password: row.password as string,
      createdAt: row.createdAt as string,
    };
  },

  async checkUserExists(email: string): Promise<boolean> {
    const result = await db.execute({
      sql: 'SELECT 1 FROM users WHERE email = ?',
      args: [email],
    });
    console.log(result);
    return result.rows.length > 0;
  },
};
