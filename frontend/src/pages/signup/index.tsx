import { useState, ReactElement } from 'react';
import { client } from '@/api/client';
import AdminLayout from '@/components/admin/layout';
import { useToast } from '@/contexts/ToastContext';
import FormError from '@/components/FormError';
import { DUPLICATE_EXECUTION, ERROR, VALIDATION_ERROR } from '@/const/ErrorMessage';
import { signupSchema, SignupFormData } from '@/schemas';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';

export default function AdminPage(): ReactElement {
  const [loading, setLoading] = useState(false);
  const { show } = useToast();

  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
  } = useForm<SignupFormData>({
    resolver: zodResolver(signupSchema),
    mode: 'onBlur',
  });

  const onSubmit = async (data: SignupFormData) => {
    setLoading(true);

    try {
      await client.auth.pre_users.$post({
        body: {
          password: data.password,
          email: data.email,
        }
      });

      show({ message: '入力されたメールアドレスにメールを送信しました', type: 'success' });
      reset();

      // 状態の更新を待ってからリダイレクト
      setTimeout(() => {
        window.location.href = '/verify-email';
      }, 1000);
    } catch (error: any) {
      if (error.response?.status === 422 && error.response?.data?.errors) {
        show({ message: VALIDATION_ERROR, type: 'error' });
      } else if (error.response?.status === 409) {
        show({ message: DUPLICATE_EXECUTION, type: 'error' });
      } else {
        show({ message: ERROR, type: 'error' });
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <AdminLayout title="新規登録" sidebar={false}>
      <div className="bg-black min-h-screen flex items-center justify-center">
        <div className="w-full max-w-md bg-[#0A0A0A] border-[1px] border-[#B1B1B1] rounded-xl p-8 pt-[3rem]">
          <div className="text-center mb-8">
            <h1 className="text-white text-3xl font-bold mb-2">Lexis</h1>
            <h2 className="text-white text-2xl">新規登録</h2>
          </div>

          <form className="mb-[1rem]" onSubmit={handleSubmit(onSubmit)}>
            <div className="mb-2">
              <label htmlFor="email" className="block text-white mb-1 font-bold">
                メールアドレス
              </label>
              <input
                type="email"
                id="email"
                placeholder="mail@example.com"
                className={`w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none ${
                  errors.email ? 'border-2 border-red-500' : ''
                }`}
                {...register('email')}
              />
              {errors.email && (
                <FormError className="mt-2">
                  {errors.email.message}
                </FormError>
              )}
            </div>

            <div className="mb-8">
              <label htmlFor="password" className="block text-white mb-1 font-bold">
                パスワード
              </label>
              <input
                type="password"
                id="password"
                placeholder="パスワードを入力"
                className={`w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none ${
                  errors.password ? 'border-2 border-red-500' : ''
                }`}
                {...register('password')}
              />
              {errors.password && (
                <FormError className="mt-2">
                  {errors.password.message}
                </FormError>
              )}
            </div>

            <button
              type="submit"
              className="border-none w-full font-bold bg-[#3832A5] hover:bg-indigo-800 text-white py-4 rounded-lg text-center transition duration-200"
              disabled={loading}
            >
              {loading ? '処理中...' : '登録する'}
            </button>
            <div className="flex justify-center mt-4">
              <p className="text-white text-[0.8rem]">
                アカウントをお持ちの方
                <a href="/login" className="text-white hover:underline ml-8">
                  ログイン
                </a>
              </p>
            </div>
          </form>
        </div>
      </div>
    </AdminLayout>
  );
}
