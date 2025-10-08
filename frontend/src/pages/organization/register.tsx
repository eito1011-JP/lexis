import React, { useEffect, useMemo, useState } from 'react';
import AdminLayout from '@/components/admin/layout';
import { axios } from '@/api/client';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useToast } from '@/contexts/ToastContext';
import { AUTHENTICATION_FAILED, DUPLICATE_ORGANIZATION, ERROR, INVALID_AUTHENTICATION_TOKEN, VALIDATION_ERROR } from '@/const/ErrorMessage';
import { organizationSchema, OrganizationFormData } from '@/schemas';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';

export default function OrganizationRegisterPage(): React.ReactElement {
  const location = useLocation();
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const token = useMemo(() => new URLSearchParams(location.search).get('token') ?? '', [location.search]);
  const { show } = useToast();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<OrganizationFormData>({
    resolver: zodResolver(organizationSchema),
    mode: 'onBlur',
  });

  useEffect(() => {
    async function handleIdentifyToken() {
      if (!token) {
        alert('トークンが指定されていません');
        navigate('/signup');
        return;
      }
  
      try {
        // TODO: トークン検証用のaspidaエンドポイントを追加する必要があります
        await axios.get('/auth/pre-users', { params: { token } });
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
  }, [token, navigate, show]);

  const onSubmit = async (data: OrganizationFormData) => {
    if (!token) return;
    setLoading(true);
    
    try {
      // organization_uuidとtokenを含むリクエストをaxiosで送信
      await axios.post('/organizations', {
        organization_uuid: data.organization_uuid,
        organization_name: data.organization_name,
        token,
      });

      show({ message: '組織名を登録しました', type: 'success' });

      setTimeout(() => {
        navigate('/documents');
      }, 1000);
    } catch (error: any) {
      if (error.response?.status === 422 && error.response?.data?.errors) {
        show({ message: VALIDATION_ERROR, type: 'error' });
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
          <form onSubmit={handleSubmit(onSubmit)}>
            <div className="mb-6">
              <label className="block text-white mb-2 font-bold">組織ID</label>
              <input
                className={`w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none ${
                  errors.organization_uuid ? 'border-2 border-red-500' : ''
                }`}
                placeholder="nexis-inc"
                {...register('organization_uuid')}
              />
              {errors.organization_uuid && (
                <div className="mt-2">
                  <p className="text-red-500 text-sm">{errors.organization_uuid.message}</p>
                </div>
              )}
            </div>
            <div className="mb-8">
              <label className="block text-white mb-2 font-bold">組織名</label>
              <input
                className={`w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none ${
                  errors.organization_name ? 'border-2 border-red-500' : ''
                }`}
                placeholder="株式会社Nexis"
                {...register('organization_name')}
              />
              {errors.organization_name && (
                <div className="mt-2">
                  <p className="text-red-500 text-sm">{errors.organization_name.message}</p>
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
