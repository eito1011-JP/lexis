import { z } from 'zod';

/**
 * 修正リクエスト適用のバリデーションスキーマ
 * Backend: ApplyFixRequestRequest
 */
export const applyFixRequestSchema = z.object({
  token: z
    .string()
    .min(1, 'トークンを入力してください')
    .max(255, 'トークンは255文字以内である必要があります'),
});

export type ApplyFixRequestFormData = z.infer<typeof applyFixRequestSchema>;

