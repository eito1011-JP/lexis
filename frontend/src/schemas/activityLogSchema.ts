import { z } from 'zod';

/**
 * アクティビティログ作成フォームのバリデーションスキーマ
 * Backend: CreateActivityLogRequest
 */
export const createActivityLogSchema = z.object({
  pull_request_id: z
    .number()
    .int()
    .positive('プルリクエストIDは正の整数である必要があります'),
  action: z.enum(
    [
      'fix_request_sent',
      'fix_request_applied',
      'assigned_reviewer',
      'reviewer_approved',
      'commented',
      'pull_request_merged',
      'pull_request_closed',
      'pull_request_reopened',
      'pull_request_title_edited',
    ],
    {
      message: '有効なアクションを指定してください',
    }
  ),
});

export type CreateActivityLogFormData = z.infer<typeof createActivityLogSchema>;

