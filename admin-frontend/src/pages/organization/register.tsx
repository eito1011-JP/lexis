import React, { useEffect, useMemo, useState } from 'react';
import AdminLayout from '@/components/admin/layout';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { Link, useLocation, useNavigate } from 'react-router-dom';

export default function OrganizationRegisterPage(): React.ReactElement {
  const location = useLocation();
  const navigate = useNavigate();
  const [organizationId, setOrganizationId] = useState('');
  const [organizationName, setOrganizationName] = useState('');
  const [loading, setLoading] = useState(false);
  const token = useMemo(() => new URLSearchParams(location.search).get('token') ?? '', [location.search]);

  useEffect(() => {
    async function handleIdentifyToken() {
      if (!token) {
        alert('トークンが指定されていません');
        navigate('/admin/signup');
        return;
      }
  
      try {
        await apiClient.get(API_CONFIG.ENDPOINTS.PRE_USERS_IDENTIFY, { params: { token } });
        // トークンが有効な場合、何もしない（フォームを表示する）
      } catch (error: any) {
        console.error('Token validation error:', error);
        
        // レスポンスが存在し、ステータスコードが取得できる場合
        if (error.response?.status === 401) {
          alert('無効なトークンです');
        } else {
          // 401以外のHTTPエラーレスポンス
          alert(`エラーが発生しました (ステータス: ${error.response.status})`);
        }

        navigate('/admin/signup');
      }
    }
    
    handleIdentifyToken();
  }, [token, navigate]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;
    setLoading(true);
    try {
      await apiClient.post(API_CONFIG.ENDPOINTS.ORGANIZATIONS_CREATE, {
        organization_uuid: organizationId,
        organization_name: organizationName,
        token,
      });
      navigate('/admin/documents');
    } catch (e) {
      alert('登録に失敗しました');
    } finally {
      setLoading(false);
    }
  };

  return (
    <AdminLayout title="組織登録" sidebar={false}>
      <div className="bg-black min-h-screen flex items-center justify-center">
        <div className="w-full max-w-[600px] bg-[#0A0A0A] border-[1px] border-[#B1B1B1] rounded-xl p-12">
          <h2 className="text-white text-3xl font-bold text-center mb-10">組織名を入力してください</h2>
          <form onSubmit={handleSubmit}>
            <div className="mb-6">
              <label className="block text-white mb-2 font-bold">組織ID</label>
              <input
                className="w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none"
                placeholder="nexis-inc"
                value={organizationId}
                onChange={(e) => setOrganizationId(e.target.value)}
                required
              />
            </div>
            <div className="mb-8">
              <label className="block text-white mb-2 font-bold">組織名</label>
              <input
                className="w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none"
                placeholder="株式会社Nexis"
                value={organizationName}
                onChange={(e) => setOrganizationName(e.target.value)}
                required
              />
            </div>
            <button
              type="submit"
              className="border-none w-full font-bold bg-[#3832A5] hover:bg-indigo-800 text-white py-4 rounded-lg text-center transition duration-200"
              disabled={loading}
            >
              {loading ? '処理中...' : '確定する'}
            </button>
          </form>
          <div className="text-center mt-6">
            <Link to="/organization/join" className="text-[#B1B1B1] underline">
              既存の組織に参加することはこちら
            </Link>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}


