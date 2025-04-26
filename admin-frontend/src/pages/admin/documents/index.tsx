import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import AdminLayout from '../../../components/layout';
import { useSessionCheck } from '../../../hooks/useSessionCheck';
import { apiClient } from '../../../api/client';

type Item = {
  type: 'category' | 'file';
  slug: string;
  label: string;
};

export default function DocumentsPage() {
  const { isLoading } = useSessionCheck();
  const [items, setItems] = useState<Item[]>([]);
  const [isLoadingItems, setIsLoadingItems] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchDocuments = async () => {
      try {
        setIsLoadingItems(true);
        const response = await apiClient.get('/admin/documents');
        setItems(response.items || []);
      } catch (err) {
        console.error('ドキュメント取得エラー:', err);
        setError('ドキュメントの取得に失敗しました');
      } finally {
        setIsLoadingItems(false);
      }
    };

    if (!isLoading) {
      fetchDocuments();
    }
  }, [isLoading]);

  if (isLoading) {
    return (
      <AdminLayout title="ドキュメント管理">
        <div className="flex justify-center py-10">
          <div className="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-white"></div>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout title="ドキュメント管理">
      <div className="flex justify-between mb-6">
        <h2 className="text-xl">カテゴリ一覧</h2>
        <Link 
          to="/documents/create" 
          className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded"
        >
          新規作成
        </Link>
      </div>

      {error && (
        <div className="bg-red-900 text-white p-3 rounded mb-4">
          {error}
        </div>
      )}

      {isLoadingItems ? (
        <div className="flex justify-center py-6">
          <div className="animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-white"></div>
        </div>
      ) : items.length === 0 ? (
        <p className="text-gray-400 py-4">ドキュメントがありません</p>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {items.map((item, index) => (
            <div
              key={index}
              className="p-4 bg-gray-900 border border-gray-800 rounded-md hover:bg-gray-800"
            >
              <Link 
                to={`/documents/${item.slug}`}
                className="text-lg font-medium hover:text-blue-400"
              >
                {item.label}
              </Link>
              <p className="text-gray-400 text-sm mt-1">
                {item.type === 'category' ? 'カテゴリ' : 'ドキュメント'}
              </p>
            </div>
          ))}
        </div>
      )}
    </AdminLayout>
  );
} 