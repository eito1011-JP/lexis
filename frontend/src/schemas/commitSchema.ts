import { z } from 'zod';

/**
 * コミット作成フォームのバリデーションスキーマ
 * Backend: StoreRequest (Commit)
 */
export const createCommitSchema = z.object({
  pull_request_id: z
    .number()
    .int()
    .positive('プルリクエストIDは正の整数である必要があります'),
  message: z
    .string()
    .min(1, '編集内容を入力してください')
    .max(50, '編集内容は50文字以内である必要があります'),
});

export type CreateCommitFormData = z.infer<typeof createCommitSchema>;

