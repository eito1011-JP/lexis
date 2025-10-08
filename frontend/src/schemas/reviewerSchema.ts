import { z } from 'zod';

/**
 * プルリクエストレビュアー設定のバリデーションスキーマ
 * Backend: SetPullRequestReviewersRequest
 */
export const setPullRequestReviewersSchema = z.object({
  pull_request_id: z
    .number()
    .int()
    .positive('プルリクエストIDは正の整数である必要があります'),
  emails: z
    .array(z.string().email('有効なメールアドレスを入力してください'))
    .min(1, 'レビュアーを最低1人は指定してください')
    .max(15, 'レビュアーは15人までです'),
});

export type SetPullRequestReviewersFormData = z.infer<typeof setPullRequestReviewersSchema>;

