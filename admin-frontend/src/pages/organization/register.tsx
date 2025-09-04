import React, { useEffect, useMemo, useState } from 'react';
import AdminLayout from '@/components/admin/layout';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useToast } from '@/contexts/ToastContext';
import { AUTHENTICATION_FAILED, DUPLICATE_ORGANIZATION, ERROR, INVALID_AUTHENTICATION_TOKEN } from '@/const/ErrorMessage';

export default function OrganizationRegisterPage(): React.ReactElement {
  const location = useLocation();
  const navigate = useNavigate();
  const [organizationId, setOrganizationId] = useState('');
  const [organizationName, setOrganizationName] = useState('');
  const [loading, setLoading] = useState(false);
  const token = useMemo(() => new URLSearchParams(location.search).get('token') ?? '', [location.search]);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});
  const { show } = useToast();

  useEffect(() => {
    async function handleIdentifyToken() {
      if (!token) {
        alert('トークンが指定されていません');
        navigate('/signup');
        return;
      }
  
      try {
        await apiClient.get(API_CONFIG.ENDPOINTS.PRE_USERS_IDENTIFY, { params: { token } });
        // トークンが有効な場合、何もしない（フォームを表示する）
      } catch (error: any) {
        if (error.response?.status === 401) {
          show({ message: INVALID_AUTHENTICATION_TOKEN, type: 'error' });
          navigate('/signup');
        } else {
          show({ message: ERROR, type: 'error' });
        }
      }
    }
    
    handleIdentifyToken();
  }, [token, navigate]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;
    setLoading(true);
    setValidationErrors({}); // バリデーションエラーをクリア
    
    try {
      await apiClient.post(API_CONFIG.ENDPOINTS.ORGANIZATIONS_CREATE, {
        organization_uuid: organizationId,
        organization_name: organizationName,
        token,
      });

      show({ message: '組織名を登録しました', type: 'success' });

      setTimeout(() => {
        navigate('/documents');
      }, 1000);
    } catch (error: any) {
      if (error.response?.status === 422 && error.response?.data?.errors) {
        setValidationErrors(error.response.data.errors);
      } else if (error.response?.status === 401) {
        show({ message: AUTHENTICATION_FAILED, type: 'error' });
        navigate('/signup');
      } else if (error.response?.status === 409) {
        show({ message: DUPLICATE_ORGANIZATION, type: 'error' });
        navigate('/signup');
      } else {
        show({ message: ERROR, type: 'error' });
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <AdminLayout title="組織登録" sidebar={false}>
      <div className="bg-black min-h-screen flex items-center justify-center">
        <div className="w-full max-w-[500px] bg-[#0A0A0A] border-[1px] border-[#B1B1B1] rounded-xl p-12">
          <h2 className="text-white text-2xl font-bold text-center mb-10">組織名を入力してください</h2>
          <form onSubmit={handleSubmit}>
            <div className="mb-6">
              <label className="block text-white mb-2 font-bold">組織ID</label>
              <input
                className={`w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none ${
                  validationErrors.organization_uuid ? 'border-2 border-red-500' : ''
                }`}
                placeholder="nexis-inc"
                value={organizationId}
                onChange={(e) => setOrganizationId(e.target.value)}
                required
              />
              {validationErrors.organization_uuid && (
                <div className="mt-2">
                  {validationErrors.organization_uuid.map((error, index) => (
                    <p key={index} className="text-red-500 text-sm">{error}</p>
                  ))}
                </div>
              )}
            </div>
            <div className="mb-8">
              <label className="block text-white mb-2 font-bold">組織名</label>
              <input
                className={`w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none ${
                  validationErrors.organization_name ? 'border-2 border-red-500' : ''
                }`}
                placeholder="株式会社Nexis"
                value={organizationName}
                onChange={(e) => setOrganizationName(e.target.value)}
                required
              />
              {validationErrors.organization_name && (
                <div className="mt-2">
                  {validationErrors.organization_name.map((error, index) => (
                    <p key={index} className="text-red-500 text-sm">{error}</p>
                  ))}
                </div>
              )}
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
