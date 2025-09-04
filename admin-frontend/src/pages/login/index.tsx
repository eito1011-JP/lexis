import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import AdminLayout from '@/components/admin/layout';
import { useState, FormEvent, ReactElement } from 'react';
import { useNavigate } from 'react-router-dom';
import { useToast } from '@/contexts/ToastContext';
import { VALIDATION_ERROR, WRONG_EMAIL_OR_PASSWORD, NO_ACCOUNT, ERROR, TOO_MANY_REQUESTS } from '@/const/ErrorMessage';

export default function LoginPage(): ReactElement {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});
  const navigate = useNavigate();
  const { show } = useToast();

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setValidationErrors({});

    try {
      const signin = await apiClient.post(API_CONFIG.ENDPOINTS.SIGNIN_WITH_EMAIL, {
        email,
        password,
      });

      localStorage.setItem('access_token', signin.token);
      show({ message: 'ログインに成功しました', type: 'success' });
      // フォームをリセット
      setEmail('');
      setPassword('');


      // 状態の更新を待ってからリダイレクト
      setTimeout(() => {
        navigate('/documents');
      }, 1000);
    } catch (error: any) {
      const status = error?.response?.status;
      if (status === 422) {
        setValidationErrors(error.response?.data?.errors ?? {});
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

          <form className="mb-[1rem]" onSubmit={handleSubmit}>
            <div className="mb-2">
              <label htmlFor="email" className="block text-white mb-1 font-bold">
                メールアドレス
              </label>
              <input
                type="email"
                id="email"
                placeholder="mail@example.com"
                className="w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none"
                value={email}
                onChange={e => setEmail(e.target.value)}
                required
              />
              {validationErrors.email && (
                <p className="text-red-400 text-sm mt-1">{validationErrors.email[0]}</p>
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
                className="w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none"
                value={password}
                onChange={e => setPassword(e.target.value)}
                required
              />
              {validationErrors.password && (
                <p className="text-red-400 text-sm mt-1">{validationErrors.password[0]}</p>
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
