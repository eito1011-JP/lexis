import { z } from 'zod';

/**
 * メール認証送信フォームのバリデーションスキーマ
 * Backend: SendAuthnEmailRequest
 */
export const sendAuthnEmailSchema = z.object({
  email: z
    .string()
    .min(1, 'メールアドレスを入力してください')
    .email('有効なメールアドレスを入力してください'),
  password: z
    .string()
    .min(1, 'パスワードを入力してください')
    .min(8, 'パスワードは8文字以上である必要があります'),
});

/**
 * メールでのサインインフォームのバリデーションスキーマ
 * Backend: SigninWithEmailRequest
 */
export const signinWithEmailSchema = z.object({
  email: z
    .string()
    .min(1, 'メールアドレスを入力してください')
    .email('有効なメールアドレスを入力してください')
    .max(254, 'メールアドレスは254文字以内である必要があります'),
  password: z
    .string()
    .min(1, 'パスワードを入力してください')
    .min(8, 'パスワードは8文字以上である必要があります'),
});

/**
 * トークン認証のバリデーションスキーマ
 * Backend: IdentifyTokenRequest
 */
export const identifyTokenSchema = z.object({
  token: z
    .string()
    .min(1, 'トークンを入力してください')
    .max(64, 'トークンは64文字以内である必要があります'),
});

/**
 * ログインフォームのバリデーションスキーマ（後方互換性のため保持）
 */
export const loginSchema = signinWithEmailSchema;

/**
 * サインアップフォームのバリデーションスキーマ（後方互換性のため保持）
 */
export const signupSchema = sendAuthnEmailSchema;

export type SendAuthnEmailFormData = z.infer<typeof sendAuthnEmailSchema>;
export type SigninWithEmailFormData = z.infer<typeof signinWithEmailSchema>;
export type IdentifyTokenFormData = z.infer<typeof identifyTokenSchema>;
export type LoginFormData = z.infer<typeof loginSchema>;
export type SignupFormData = z.infer<typeof signupSchema>;

