import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import AdminLayout from '../../../components/layout';
import { useSessionCheck } from '../../../hooks/useSessionCheck';
import { apiClient } from '../../../api/client';

// アイテムの型定義
type Item = {
  type: 'category' | 'file';
  slug: string;
  label: string;
  position?: number;
  description?: string;
};

// パンくずリストアイテムの型定義
type BreadcrumbItem = {
  name: string;
  path: string;
};

export default function DocumentDetailPage() {
  const params = useParams<{ slug: string }>();
  const slug = params.slug;
  console.log('Current slug parameter:', slug);

  const { isLoading } = useSessionCheck();
  const [items, setItems] = useState<Item[]>([]);
  const [breadcrumbs, setBreadcrumbs] = useState<BreadcrumbItem[]>([]);
  const [itemsLoading, setItemsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!slug) return;

    // カテゴリ詳細データを取得
    const getCategoryDetails = async () => {
      try {
        setItemsLoading(true);
        const response = await apiClient.get(`/admin/documents/${slug}`);
        
        if (response && response.items) {
          setItems(response.items);
          setBreadcrumbs(response.breadcrumbs || []);
        }
      } catch (err) {
        console.error('カテゴリ詳細取得エラー:', err);
        setError('カテゴリ詳細の取得に失敗しました');
      } finally {
        setItemsLoading(false);
      }
    };

    if (!isLoading) {
      getCategoryDetails();
    }
  }, [slug, isLoading]);

  // セッション確認中はローディング表示
  if (isLoading) {
    return (
      <AdminLayout title="読み込み中...">
        <div className="flex justify-center py-10">
          <div className="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-white"></div>
        </div>
      </AdminLayout>
    );
  }

  // コンテンツセクション
  const renderContentsSection = () => {
    if (itemsLoading) {
      return (
        <div className="flex justify-center py-6">
          <div className="animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-white"></div>
        </div>
      );
    }

    if (items.length === 0) {
      return <p className="text-gray-400 py-4">コンテンツがありません</p>;
    }

    return (
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {items.map((item, index) => (
          <div
            key={index}
            className="flex items-center p-3 bg-gray-900 rounded-md border border-gray-800 hover:bg-gray-800 cursor-pointer"
          >
            {item.type === 'category' ? (
              <Link to={`/documents/${item.slug}`} className="text-white hover:underline">
                {item.label}
              </Link>
            ) : (
              <Link to={`/documents/edit/${item.slug}`} className="text-white hover:underline">
                {item.label}
              </Link>
            )}
          </div>
        ))}
      </div>
    );
  };

  // パンくずリスト
  const renderBreadcrumbs = () => {
    if (!breadcrumbs || breadcrumbs.length === 0) return null;

    return (
      <div className="flex items-center text-sm text-gray-400 mb-4">
        <Link to="/documents" className="hover:text-white">
          ドキュメント
        </Link>
        {breadcrumbs.map((item, index) => (
          <React.Fragment key={index}>
            <span className="mx-2">/</span>
            <Link to={item.path} className="hover:text-white">
              {item.name}
            </Link>
          </React.Fragment>
        ))}
      </div>
    );
  };

  return (
    <AdminLayout title={`${slug ? slug : 'カテゴリ詳細'}`}>
      <div className="flex flex-col h-full">
        <div className="mb-6">
          {renderBreadcrumbs()}
          <h1 className="text-2xl font-bold mb-4">
            {breadcrumbs.length > 0 ? breadcrumbs[breadcrumbs.length - 1].name : slug}
          </h1>

          {/* エラー表示 */}
          {error && (
            <div className="bg-red-900 text-white p-3 rounded mb-4">
              {error}
            </div>
          )}

          {/* コンテンツ */}
          <div className="bg-gray-900 p-4 rounded-md mb-6">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl">コンテンツ</h2>
              <div className="flex space-x-2">
                <button
                  className="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm"
                >
                  カテゴリ作成
                </button>
                <button
                  className="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm"
                >
                  ドキュメント作成
                </button>
              </div>
            </div>
            {renderContentsSection()}
          </div>
        </div>
      </div>
    </AdminLayout>
  );
} 