import { z } from 'zod';

/**
 * ユーザーブランチ更新フォームのバリデーションスキーマ
 * Backend: UpdateUserBranchRequest
 */
export const updateUserBranchSchema = z.object({
  user_branch_id: z
    .number()
    .int()
    .positive('ユーザーブランチIDは正の整数である必要があります'),
  is_active: z
    .boolean({
      message: 'アクティブ状態はtrue/falseで指定してください',
    }),
});

export type UpdateUserBranchFormData = z.infer<typeof updateUserBranchSchema>;

