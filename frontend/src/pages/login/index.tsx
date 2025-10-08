import AdminLayout from '@/components/admin/layout';
import { useState, ReactElement } from 'react';
import { useNavigate } from 'react-router-dom';
import { useToast } from '@/contexts/ToastContext';
import { useAuth } from '@/contexts/AuthContext';
import { VALIDATION_ERROR, WRONG_EMAIL_OR_PASSWORD, NO_ACCOUNT, ERROR, TOO_MANY_REQUESTS } from '@/const/ErrorMessage';
import { loginSchema, LoginFormData } from '@/schemas';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';

export default function LoginPage(): ReactElement {
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();
  const { show } = useToast();
  const { login } = useAuth();

  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
    mode: 'onBlur',
  });

  const onSubmit = async (data: LoginFormData) => {
    setLoading(true);

    try {
      await login(data.email, data.password);

      show({ message: 'ログインに成功しました', type: 'success' });
      reset();

      // 認証状態が更新されるのを待ってからリダイレクト
      setTimeout(() => {
        navigate('/documents');
      }, 1000);
    } catch (error: any) {
      const status = error?.response?.status;
      if (status === 422) {
        show({ message: VALIDATION_ERROR, type: 'error' });
      } else if (status === 401) {
        show({ message: WRONG_EMAIL_OR_PASSWORD, type: 'error' });
      } else if (status === 404) {
        show({ message: NO_ACCOUNT, type: 'error' });
      } else if (status === 429) {
        show({ message: TOO_MANY_REQUESTS, type: 'error' });
      } else {
        show({ message: ERROR, type: 'error' });
      }
    } finally {
      setLoading(false);
    }
  };

  // セッション確認中はローディング表示
  if (loading) {
    return (
      <AdminLayout title="読み込み中..." sidebar={false}>
        <div className="bg-black min-h-screen flex items-center justify-center">
          <div className="flex flex-col items-center">
            <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
          </div>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout title="ログイン" sidebar={false}>
      <div className="bg-black min-h-screen flex items-center justify-center">
        <div className="w-full max-w-md bg-[#0A0A0A] border-[1px] border-[#B1B1B1] rounded-xl p-8 pt-[3rem]">
          <div className="text-center mb-8">
            <h1 className="text-white text-3xl font-bold mb-2">Lexis</h1>
            <h2 className="text-white text-2xl">ログイン</h2>
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
                <p className="text-red-400 text-sm mt-1">{errors.email.message}</p>
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
                <p className="text-red-400 text-sm mt-1">{errors.password.message}</p>
              )}
            </div>
            <button
              type="submit"
              className="border-none w-full font-bold bg-[#3832A5] hover:bg-indigo-800 text-white py-4 rounded-lg text-center transition duration-200"
              disabled={loading}
            >
              {loading ? '処理中...' : 'ログインする'}
            </button>
            <div className="flex justify-center mt-4">
              <p className="text-white text-[0.8rem]">
                アカウントをお持ちでない方
                <a href="/signup" className="text-white hover:underline ml-8">
                  新規登録
                </a>
              </p>
            </div>
          </form>
        </div>
      </div>
    </AdminLayout>
  );
}
