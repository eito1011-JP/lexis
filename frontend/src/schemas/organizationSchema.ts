import { z } from 'zod';

/**
 * 組織登録フォームのバリデーションスキーマ
 * Backend: CreateOrganizationRequest
 */
export const organizationSchema = z.object({
  organization_uuid: z
    .string()
    .min(1, '組織IDを入力してください')
    .max(64, '組織IDは64文字以内である必要があります')
    .regex(
      /^[a-z-]+$/,
      '組織IDは小文字の英字とハイフンのみ使用できます'
    ),
  organization_name: z
    .string()
    .min(1, '組織名を入力してください')
    .max(255, '組織名は255文字以内である必要があります'),
  token: z
    .string()
    .min(1, 'トークンを入力してください')
    .max(64, 'トークンは64文字以内である必要があります'),
});

export type OrganizationFormData = z.infer<typeof organizationSchema>;

