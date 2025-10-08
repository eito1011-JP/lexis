import { z } from 'zod';

/**
 * コメント投稿フォームのバリデーションスキーマ
 * Backend: PostCommentRequest
 */
export const postCommentSchema = z.object({
  pull_request_id: z
    .number()
    .int()
    .positive('プルリクエストIDは正の整数である必要があります'),
  content: z
    .string()
    .min(1, 'コメントを入力してください')
    .max(65535, 'コメントは65535文字以内である必要があります'),
});

export type PostCommentFormData = z.infer<typeof postCommentSchema>;

