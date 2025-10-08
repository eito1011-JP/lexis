import { z } from 'zod';

/**
 * カテゴリ作成フォームのバリデーションスキーマ
 * Backend: CreateDocumentCategoryRequest
 */
export const createCategorySchema = z.object({
  title: z
    .string()
    .min(1, 'タイトルを入力してください'),
  description: z
    .string()
    .min(1, '説明を入力してください'),
  parent_entity_id: z
    .number()
    .int()
    .optional()
    .nullable(),
});

/**
 * カテゴリ更新フォームのバリデーションスキーマ
 * Backend: UpdateDocumentCategoryRequest
 */
export const updateCategorySchema = z.object({
  category_entity_id: z
    .number()
    .int()
    .positive('カテゴリIDは正の整数である必要があります'),
  title: z
    .string()
    .min(1, 'タイトルを入力してください'),
  description: z
    .string()
    .min(1, '説明を入力してください'),
});

/**
 * カテゴリ削除のバリデーションスキーマ
 * Backend: DeleteDocumentCategoryRequest
 */
export const deleteCategorySchema = z.object({
  category_entity_id: z
    .number()
    .int()
    .positive('カテゴリIDは正の整数である必要があります'),
});

export type CreateCategoryFormData = z.infer<typeof createCategorySchema>;
export type UpdateCategoryFormData = z.infer<typeof updateCategorySchema>;
export type DeleteCategoryFormData = z.infer<typeof deleteCategorySchema>;

