import { apiClient } from '@site/src/components/admin/api/client';
import { API_CONFIG } from '@site/src/components/admin/api/config';
import AdminLayout from '@site/src/components/admin/layout';
import React, { useState, FormEvent } from 'react';

export default function AdminPage(): JSX.Element {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    setSuccess('');

    try {
      const data = await apiClient.post(API_CONFIG.ENDPOINTS.SIGNUP, { 
        email, 
        password 
      });
      
      setSuccess(data.message || '登録が完了しました');
      // フォームをリセット
      setEmail('');
      setPassword('');
    } catch (err) {
      console.error('Error:', err);
      setError(err instanceof Error ? err.message : '通信エラーが発生しました');
    } finally {
      setLoading(false);
    }
  };

  return (
    <AdminLayout title="メディア" sidebar={false}>
      <div className="bg-black min-h-screen flex items-center justify-center">
        <div className="w-full max-w-md bg-[#0A0A0A] border-[1px] border-[#B1B1B1] rounded-xl p-8 pt-[3rem]">
          <div className="text-center mb-8">
            <h1 className="text-white text-3xl font-bold mb-2">Lexis</h1>
            <h2 className="text-white text-2xl">新規登録</h2>
          </div>
          
          {error && (
            <div className="mb-4 p-3 bg-red-500 text-white rounded-lg">
              {error}
            </div>
          )}
          
          {success && (
            <div className="mb-4 p-3 bg-green-500 text-white rounded-lg">
              {success}
            </div>
          )}
          
          <form className='mb-[1rem]' onSubmit={handleSubmit}>
            <div className="mb-2">
              <label htmlFor="email" className="block text-white mb-1 font-bold">メールアドレス</label>
              <input 
                type="email" 
                id="email" 
                placeholder="mail@example.com"
                className="w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            
            <div className="mb-8">
              <label htmlFor="password" className="block text-white mb-1 font-bold">パスワード</label>
              <input 
                type="password" 
                id="password" 
                placeholder="パスワードを入力"
                className="w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                minLength={8}
              />
            </div>
            
            <button 
              type="submit" 
              className="border-none w-full font-bold bg-[#3832A5] hover:bg-indigo-800 text-white py-4 rounded-lg text-center transition duration-200"
              disabled={loading}
            >
              {loading ? '処理中...' : '登録する'}
            </button>
          </form>
        </div>
      </div>
    </AdminLayout>
  );
}
