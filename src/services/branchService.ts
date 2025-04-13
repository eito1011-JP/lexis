import { v4 as uuidv4 } from 'uuid';
import { db } from '../lib/db';

export interface UserBranch {
  id: string;
  userId: string;
  branchName: string;
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
  prStatus: 'none' | 'pending' | 'created';
  prUrl?: string;
}

export const branchService = {
  // ユーザーのアクティブブランチを取得
  async getActiveBranch(userId: string): Promise<UserBranch | null> {
    try {
      const result = await db.execute({
        sql: 'SELECT * FROM user_branches WHERE user_id = ? AND is_active = 1 LIMIT 1',
        args: [userId],
      });

      if (result.rows.length === 0) {
        return null;
      }

      const branch = result.rows[0];
      return {
        id: branch.id as string,
        userId: branch.user_id as string,
        branchName: branch.branch_name as string,
        isActive: Boolean(branch.is_active),
        createdAt: branch.created_at as string,
        updatedAt: branch.updated_at as string,
        prStatus: branch.pr_status as 'none' | 'pending' | 'created',
        prUrl: branch.pr_url as string | undefined,
      };
    } catch (error) {
      console.error('アクティブブランチ取得エラー:', error);
      return null;
    }
  },

  // ユーザーのすべてのブランチを取得
  async getUserBranches(userId: string): Promise<UserBranch[]> {
    try {
      const result = await db.execute({
        sql: 'SELECT * FROM user_branches WHERE user_id = ? ORDER BY updated_at DESC',
        args: [userId],
      });

      return result.rows.map(branch => ({
        id: branch.id as string,
        userId: branch.user_id as string,
        branchName: branch.branch_name as string,
        isActive: Boolean(branch.is_active),
        createdAt: branch.created_at as string,
        updatedAt: branch.updated_at as string,
        prStatus: branch.pr_status as 'none' | 'pending' | 'created',
        prUrl: branch.pr_url as string | undefined,
      }));
    } catch (error) {
      console.error('ユーザーブランチ取得エラー:', error);
      return [];
    }
  },

  // 新しいブランチを作成
  async createBranch(userId: string, branchName: string): Promise<UserBranch | null> {
    const now = new Date().toISOString();
    const branchId = uuidv4();

    try {
      // 前のアクティブブランチを非アクティブに設定
      await db.execute({
        sql: 'UPDATE user_branches SET is_active = 0 WHERE user_id = ? AND is_active = 1',
        args: [userId],
      });

      // 新しいブランチを作成
      await db.execute({
        sql: `
          INSERT INTO user_branches 
          (id, user_id, branch_name, is_active, created_at, updated_at, pr_status) 
          VALUES (?, ?, ?, 1, ?, ?, 'none')
        `,
        args: [branchId, userId, branchName, now, now],
      });

      return {
        id: branchId,
        userId,
        branchName,
        isActive: true,
        createdAt: now,
        updatedAt: now,
        prStatus: 'none',
      };
    } catch (error) {
      console.error('ブランチ作成エラー:', error);
      return null;
    }
  },

  // ブランチの状態を更新
  async updateBranchStatus(
    branchId: string,
    updates: Partial<Pick<UserBranch, 'isActive' | 'prStatus' | 'prUrl'>>
  ): Promise<boolean> {
    try {
      const updateFields = [];
      const args = [];

      if ('isActive' in updates) {
        updateFields.push('is_active = ?');
        args.push(updates.isActive ? 1 : 0);
      }

      if ('prStatus' in updates) {
        updateFields.push('pr_status = ?');
        args.push(updates.prStatus);
      }

      if ('prUrl' in updates) {
        updateFields.push('pr_url = ?');
        args.push(updates.prUrl);
      }

      updateFields.push('updated_at = ?');
      args.push(new Date().toISOString());

      args.push(branchId);

      await db.execute({
        sql: `UPDATE user_branches SET ${updateFields.join(', ')} WHERE id = ?`,
        args,
      });

      return true;
    } catch (error) {
      console.error('ブランチ状態更新エラー:', error);
      return false;
    }
  },

  // ブランチをアクティブに設定（他のブランチは非アクティブになります）
  async setActiveBranch(userId: string, branchId: string): Promise<boolean> {
    try {
      // トランザクション開始
      await db.execute({ sql: 'BEGIN TRANSACTION' });

      // すべてのブランチを非アクティブに設定
      await db.execute({
        sql: 'UPDATE user_branches SET is_active = 0, updated_at = ? WHERE user_id = ?',
        args: [new Date().toISOString(), userId],
      });

      // 指定したブランチをアクティブに設定
      await db.execute({
        sql: 'UPDATE user_branches SET is_active = 1, updated_at = ? WHERE id = ? AND user_id = ?',
        args: [new Date().toISOString(), branchId, userId],
      });

      // トランザクション終了
      await db.execute({ sql: 'COMMIT' });

      return true;
    } catch (error) {
      console.error('アクティブブランチ設定エラー:', error);
      await db.execute({ sql: 'ROLLBACK' });
      return false;
    }
  },
}; 