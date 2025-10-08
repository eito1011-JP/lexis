import { z } from 'zod';

/**
 * プルリクエスト作成フォームのバリデーションスキーマ
 * Backend: CreatePullRequestRequest
 */
export const createPullRequestSchema = z.object({
  organization_id: z
    .number()
    .int()
    .positive('組織IDは正の整数である必要があります'),
  user_branch_id: z
    .number()
    .int()
    .positive('ユーザーブランチIDは正の整数である必要があります'),
  title: z
    .string()
    .min(1, 'タイトルを入力してください')
    .max(255, 'タイトルは255文字以内である必要があります'),
  description: z
    .string()
    .optional()
    .nullable(),
  reviewers: z
    .array(z.string().email('有効なメールアドレスを入力してください'))
    .max(15, 'レビュアーは15人までです')
    .optional()
    .nullable(),
});

/**
 * プルリクエスト更新フォームのバリデーションスキーマ
 * Backend: UpdateRequest (PullRequest)
 */
export const updatePullRequestSchema = z.object({
  pull_request_id: z
    .number()
    .int()
    .positive('プルリクエストIDは正の整数である必要があります'),
  title: z
    .string()
    .min(1, 'タイトルを入力してください')
    .max(255, 'タイトルは255文字以内である必要があります')
    .optional()
    .nullable(),
  description: z
    .string()
    .optional()
    .nullable(),
});

/**
 * 修正リクエスト送信のバリデーションスキーマ
 * Backend: SendFixRequest
 */
export const sendFixRequestSchema = z.object({
  document_versions: z
    .array(
      z.object({
        id: z.number().int().positive(),
        content: z.string().min(1),
        sidebar_label: z.string().min(1).max(255),
        slug: z.string().min(1),
      })
    )
    .optional()
    .nullable(),
  document_categories: z
    .array(
      z.object({
        id: z.number().int().positive(),
        sidebar_label: z.string().min(1).max(255),
        description: z.string().min(1),
        slug: z.string().min(1),
      })
    )
    .optional()
    .nullable(),
});

export type CreatePullRequestFormData = z.infer<typeof createPullRequestSchema>;
export type UpdatePullRequestFormData = z.infer<typeof updatePullRequestSchema>;
export type SendFixRequestFormData = z.infer<typeof sendFixRequestSchema>;

