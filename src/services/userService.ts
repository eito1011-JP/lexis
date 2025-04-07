import { db } from '../lib/db';

export const userService = {
  async createUser(email: string, hashedPassword: string, userId: string) {
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

  async getUserByEmail(email: string) {
    const result = await db.execute({
      sql: 'SELECT id, email, password, created_at as createdAt FROM users WHERE email = ?',
      args: [email],
    });

    return result.rows.length > 0 ? result.rows[0] : null;
  },

  async checkUserExists(email: string) {
    const result = await db.execute({
      sql: 'SELECT 1 FROM users WHERE email = ?',
      args: [email],
    });
    console.log(result);
    return result.rows.length > 0;
  },
};
