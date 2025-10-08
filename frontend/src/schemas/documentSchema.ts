import { z } from 'zod';

/**
 * ドキュメント作成フォームのバリデーションスキーマ
 * Backend: CreateDocumentRequest
 */
export const createDocumentSchema = z.object({
  title: z
    .string()
    .min(1, 'タイトルを入力してください')
    .max(255, 'タイトルは255文字以内である必要があります'),
  description: z
    .string()
    .min(1, '内容を入力してください'),
  category_entity_id: z
    .number()
    .int()
    .positive('カテゴリIDは正の整数である必要があります'),
});

/**
 * ドキュメント更新フォームのバリデーションスキーマ
 * Backend: UpdateDocumentRequest
 */
export const updateDocumentSchema = z.object({
  document_entity_id: z
    .number()
    .int()
    .positive('ドキュメントIDは正の整数である必要があります'),
  title: z
    .string()
    .min(1, 'タイトルを入力してください')
    .max(255, 'タイトルは255文字以内である必要があります'),
  description: z
    .string()
    .min(1, '内容を入力してください'),
});

/**
 * ドキュメント削除のバリデーションスキーマ
 * Backend: DestroyDocumentRequest
 */
export const deleteDocumentSchema = z.object({
  document_entity_id: z
    .number()
    .int()
    .positive('ドキュメントIDは正の整数である必要があります'),
});

export type CreateDocumentFormData = z.infer<typeof createDocumentSchema>;
export type UpdateDocumentFormData = z.infer<typeof updateDocumentSchema>;
export type DeleteDocumentFormData = z.infer<typeof deleteDocumentSchema>;

