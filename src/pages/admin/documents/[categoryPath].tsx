import AdminLayout from '@site/src/components/admin/layout';
import React, { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useHistory } from '@docusaurus/router';
import Link from '@docusaurus/Link';
import { useSessionCheck } from '@site/src/hooks/useSessionCheck';
import { apiClient } from '@site/src/components/admin/api/client';

type CategoryItem = {
  type: 'category' | 'document';
  name: string;
  path: string;
  status?: string;
  lastEditor?: string;
  content?: string;
};

/**
 * 管理画面のカテゴリ内ドキュメント一覧ページコンポーネント
 */
export default function CategoryContentsPage(): JSX.Element {
  const history = useHistory();
  const { isLoading } = useSessionCheck('/admin/login', false);

  const [items, setItems] = useState<CategoryItem[]>([]);
  const [categoryParts, setCategoryParts] = useState<{ name: string; path: string }[]>([]);
  const [isLoadingContents, setIsLoadingContents] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [categoryPath, setCategoryPath] = useState<string>('');

  useEffect(() => {
    // URLからカテゴリパスを取得
    if (typeof window !== 'undefined') {
      const pathSegments = window.location.pathname.split('/');
      // "/admin/documents/slug" の "slug" 部分を取得
      const path = pathSegments.slice(3).join('/');
      setCategoryPath(path);
    }
  }, []);

  useEffect(() => {
    // カテゴリパスが取得できたらコンテンツを取得
    if (categoryPath) {
      fetchCategoryContents();
      // パンくずリスト用の階層構造を作成
      createBreadcrumbs();
    }
  }, [categoryPath]);

  const createBreadcrumbs = () => {
    if (!categoryPath) return;

    const parts = categoryPath.split('/');

    // カテゴリパスの各部分をリンク用に変換
    const breadcrumbs = parts.map((part, index) => {
      const currentPath = parts.slice(0, index + 1).join('/');
      return {
        name: part,
        path: currentPath,
      };
    });

    setCategoryParts(breadcrumbs);
  };

  const fetchCategoryContents = async () => {
    setIsLoadingContents(true);
    setError(null);

    try {
      const response = await apiClient.get(
        `/admin/documents/category-contents?slug=${categoryPath}`
      );

      if (response.items) {
        setItems(response.items);
      } else {
        setItems([]);
      }
    } catch (err) {
      console.error('カテゴリコンテンツ取得エラー:', err);
      setError('カテゴリコンテンツの取得に失敗しました');
    } finally {
      setIsLoadingContents(false);
    }
  };

  // カテゴリコンテンツのレンダリング
  const renderCategoryItems = () => {
    if (isLoadingContents) {
      return (
        <div className="flex justify-center py-6">
          <div className="animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-white"></div>
        </div>
      );
    }

    if (error) {
      return (
        <div className="p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
          {error}
        </div>
      );
    }

    if (items.length === 0) {
      return <p className="text-gray-400 py-4">コンテンツがありません</p>;
    }

    // カテゴリとドキュメントを分けて表示
    const categories = items.filter(item => item.type === 'category');
    const documents = items.filter(item => item.type === 'document');

    return (
      <div>
        {/* カテゴリ一覧 */}
        {categories.length > 0 && (
          <div className="mb-6">
            <h2 className="text-xl font-bold mb-4">カテゴリ</h2>
            <div className="grid grid-cols-2 gap-4">
              {categories.map((category, index) => (
                <div
                  key={`category-${index}`}
                  className="flex items-center p-3 bg-gray-900 rounded-md border border-gray-800 hover:bg-gray-800 cursor-pointer"
                  onClick={() => (window.location.href = `/admin/documents/${category.path}`)}
                >
                  <svg
                    className="w-5 h-5 mr-2 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                    ></path>
                  </svg>
                  <span>{category.name}</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* ドキュメント一覧 */}
        {documents.length > 0 && (
          <div>
            <h2 className="text-xl font-bold mb-4">ドキュメント</h2>
            <div className="grid grid-cols-12 border-b border-gray-700 pb-2 text-sm text-gray-400">
              <div className="col-span-4 flex items-center">
                <span>タイトル</span>
              </div>
              <div className="col-span-3">コンテンツ</div>
              <div className="col-span-3">公開ステータス</div>
              <div className="col-span-2">最終編集者</div>
            </div>

            {documents.map((doc, index) => (
              <div
                key={`doc-${index}`}
                className="grid grid-cols-12 py-4 border-b border-gray-800 hover:bg-gray-900"
              >
                <div className="col-span-4 flex items-center">
                  <Link
                    to={`/admin/documents/edit/${doc.path}`}
                    className="text-white hover:text-[#3832A5]"
                  >
                    {doc.name}
                  </Link>
                </div>
                <div className="col-span-3 truncate text-gray-400">{doc.content || '---'}</div>
                <div className="col-span-3">
                  <span
                    className={`px-2 py-1 rounded text-xs ${
                      doc.status === '公開'
                        ? 'bg-green-900/50 text-green-300'
                        : 'bg-gray-800 text-gray-400'
                    }`}
                  >
                    {doc.status || '未公開'}
                  </span>
                </div>
                <div className="col-span-2 text-gray-400">{doc.lastEditor || '---'}</div>
              </div>
            ))}
          </div>
        )}
      </div>
    );
  };

  // セッション確認中はローディング表示
  if (isLoading) {
    return (
      <AdminLayout title="読み込み中...">
        <div className="flex flex-col items-center justify-center h-full">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout title={`カテゴリ: ${categoryPath}`}>
      <div className="flex flex-col h-full">
        <div className="mb-6">
          <h1 className="text-2xl font-bold mb-4">ドキュメント</h1>

          {/* パンくずリスト */}
          <div className="flex items-center mb-6 text-sm text-gray-300">
            <Link to="/admin/documents" className="text-gray-400 hover:text-white">
              ホーム
            </Link>
            {categoryParts.map((part, index) => (
              <React.Fragment key={index}>
                <span className="mx-2">/</span>
                <Link
                  to={`/admin/documents/${part.path}`}
                  className="text-gray-400 hover:text-white"
                >
                  {part.name}
                </Link>
              </React.Fragment>
            ))}
          </div>

          {/* コンテンツ */}
          {renderCategoryItems()}
        </div>
      </div>
    </AdminLayout>
  );
}
