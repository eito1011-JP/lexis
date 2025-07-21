import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useParams, useLocation } from 'react-router-dom';
import { Toast } from '@/components/admin/Toast';
import { markdownToHtml } from '@/utils/markdownToHtml';
import { markdownStyles } from '@/styles/markdownContent';
import { API_CONFIG } from '@/components/admin/api/config';
import { apiClient } from '@/components/admin/api/client';

// 差分データの型定義
type DiffItem = {
  id: number;
  slug: string;
  sidebar_label: string;
  description?: string;
  title?: string;
  content?: string;
  position?: number;
  file_order?: number;
  parent_id?: number;
  category_id?: number;
  status: string;
  user_branch_id: number;
  created_at: string;
  updated_at: string;
};

// API レスポンスの型定義
type FixRequestDiffResponse = {
  current_pr: {
    documents: DiffItem[];
    categories: DiffItem[];
  };
  fix_request: {
    documents: DiffItem[];
    categories: DiffItem[];
  };
};

// SmartDiffValueコンポーネント
const SmartDiffValue: React.FC<{
  label: string;
  currentValue: any;
  fixRequestValue: any;
  isMarkdown?: boolean;
}> = ({ label, currentValue, fixRequestValue, isMarkdown = false }) => {
  const renderValue = (value: any) => {
    if (value === null || value === undefined) return '(なし)';
    if (typeof value === 'boolean') return value ? 'はい' : 'いいえ';
    return String(value);
  };

  const renderContent = (content: string, isMarkdown: boolean) => {
    if (!isMarkdown || !content) return content;

    try {
      const htmlContent = markdownToHtml(content);
      return (
        <div
          className="markdown-content prose prose-invert max-w-none"
          dangerouslySetInnerHTML={{ __html: htmlContent }}
        />
      );
    } catch (error) {
      return content;
    }
  };

  const hasChange = renderValue(currentValue) !== renderValue(fixRequestValue);

  return (
    <div className="mb-4">
      <label className="block text-sm font-medium text-gray-300 mb-2">{label}</label>
      
      <div className="grid grid-cols-2 gap-4">
        {/* 現在の変更提案 */}
        <div>
          <div className="text-xs text-gray-400 mb-1">現在の変更提案</div>
          <div className={`border rounded-md p-3 text-sm ${
            hasChange 
              ? 'bg-red-900/30 border-red-700 text-red-200' 
              : 'bg-gray-800 border-gray-600 text-gray-300'
          }`}>
            {renderContent(renderValue(currentValue), isMarkdown)}
          </div>
        </div>
        
        {/* 修正リクエスト */}
        <div>
          <div className="text-xs text-gray-400 mb-1">修正リクエスト</div>
          <div className={`border rounded-md p-3 text-sm ${
            hasChange 
              ? 'bg-green-900/30 border-green-700 text-green-200' 
              : 'bg-gray-800 border-gray-600 text-gray-300'
          }`}>
            {renderContent(renderValue(fixRequestValue), isMarkdown)}
          </div>
        </div>
      </div>
    </div>
  );
};

// SlugBreadcrumbコンポーネント
const SlugBreadcrumb: React.FC<{ slug: string }> = ({ slug }) => {
  const parts = slug.split('/').filter(Boolean);

  return (
    <div className="mb-4 text-sm text-gray-400">
      <span>/</span>
      {parts.map((part, index) => (
        <span key={index}>
          <span className="text-gray-300">{part}</span>
          {index < parts.length - 1 && <span>/</span>}
        </span>
      ))}
    </div>
  );
};

export default function FixRequestDetailPage(): JSX.Element {
  const { id } = useParams<{ id: string }>();
  const location = useLocation();
  // クエリパラメータからtokenを取得
  const searchParams = new URLSearchParams(location.search);
  const token = searchParams.get('token');
  console.log('token', token);
  const [diffData, setDiffData] = useState<FixRequestDiffResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  // 修正リクエスト差分データ取得
  const fetchFixRequestDiff = async () => {
    if (!id) {
      setError('プルリクエストIDが指定されていません');
      setLoading(false);
      return;
    }

    if (!token) {
      setError('トークンが指定されていません');
      setLoading(false);
      return;
    }

    try {
      setLoading(true);
      console.log('token', token);
      console.log('id', id);
      // apiClientのgetを利用し、tokenをクエリパラメータとして渡す
      const response = await apiClient.get(
        `/api/admin/fix-requests/${token}`,
        { params: { pull_request_id: id } }
      );
      setDiffData(response);
    } catch (err) {
      console.error('修正リクエスト差分取得エラー:', err);
      setError('修正リクエスト差分の取得に失敗しました');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchFixRequestDiff();
  }, [id, token]);

  // データ読み込み中
  if (loading) {
    return (
      <AdminLayout title="修正リクエスト詳細">
        <div className="flex flex-col items-center justify-center h-full">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
          <p className="text-gray-400">データを読み込み中...</p>
        </div>
      </AdminLayout>
    );
  }

  // エラー表示
  if (error) {
    return (
      <AdminLayout title="エラー">
        <div className="flex flex-col items-center justify-center h-full">
          <div className="mb-4 p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
            <div className="flex items-center">
              <svg
                className="w-5 h-5 mr-2 text-red-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              <span>{error}</span>
            </div>
          </div>
        </div>
      </AdminLayout>
    );
  }

  if (!diffData) {
    return (
      <AdminLayout title="修正リクエスト詳細">
        <div className="flex flex-col items-center justify-center h-full">
          <p className="text-gray-400">データが見つかりません</p>
        </div>
      </AdminLayout>
    );
  }

  // データをslugでマップ化する関数
  const mapBySlug = (items: DiffItem[] | null | undefined) => {
    return (items ?? []).reduce(
      (acc, item) => {
        acc[item.slug] = item;
        return acc;
      },
      {} as Record<string, DiffItem>
    );
  };

  const currentCategories = mapBySlug(diffData.current_pr.categories);
  const fixRequestCategories = mapBySlug(diffData.fix_request.categories);
  const currentDocuments = mapBySlug(diffData.current_pr.documents);
  const fixRequestDocuments = mapBySlug(diffData.fix_request.documents);

  // 全てのslugを取得（現在とリクエストの両方から）
  const allCategorySlugs = new Set([
    ...Object.keys(currentCategories),
    ...Object.keys(fixRequestCategories),
  ]);
  const allDocumentSlugs = new Set([
    ...Object.keys(currentDocuments),
    ...Object.keys(fixRequestDocuments),
  ]);

  return (
    <AdminLayout title="修正リクエスト詳細">
      <style>{markdownStyles}</style>
      {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}

      <div className="mb-20 w-full rounded-lg relative">
        {/* ヘッダー */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-white mb-4">修正リクエスト詳細</h1>
          <div className="text-gray-400">
            変更提案 #{id} に対する修正リクエストの内容確認
          </div>
        </div>

        {/* カテゴリの変更 */}
        {allCategorySlugs.size > 0 && (
          <div className="mb-8">
            <h2 className="text-xl font-bold text-white mb-6">
              📁 カテゴリの変更 × {allCategorySlugs.size}
            </h2>
            {Array.from(allCategorySlugs).map(slug => {
              const currentCategory = currentCategories[slug];
              const fixRequestCategory = fixRequestCategories[slug];
              
              return (
                <div key={slug} className="bg-gray-900/50 rounded-lg border border-gray-700 p-6 mb-6">
                  <SlugBreadcrumb slug={slug} />
                  
                  <SmartDiffValue
                    label="カテゴリ名"
                    currentValue={currentCategory?.sidebar_label}
                    fixRequestValue={fixRequestCategory?.sidebar_label}
                  />
                  
                  <SmartDiffValue
                    label="表示順"
                    currentValue={currentCategory?.position}
                    fixRequestValue={fixRequestCategory?.position}
                  />
                  
                  <SmartDiffValue
                    label="説明"
                    currentValue={currentCategory?.description}
                    fixRequestValue={fixRequestCategory?.description}
                  />
                </div>
              );
            })}
          </div>
        )}

        {/* ドキュメントの変更 */}
        {allDocumentSlugs.size > 0 && (
          <div className="mb-8">
            <h2 className="text-xl font-bold text-white mb-6">
              📝 ドキュメントの変更 × {allDocumentSlugs.size}
            </h2>
            {Array.from(allDocumentSlugs).map(slug => {
              const currentDocument = currentDocuments[slug];
              const fixRequestDocument = fixRequestDocuments[slug];
              
              return (
                <div key={slug} className="bg-gray-900/50 rounded-lg border border-gray-700 p-6 mb-6">
                  <SlugBreadcrumb slug={slug} />
                  
                  <SmartDiffValue
                    label="タイトル"
                    currentValue={currentDocument?.sidebar_label}
                    fixRequestValue={fixRequestDocument?.sidebar_label}
                  />
                  
                  <SmartDiffValue
                    label="公開設定"
                    currentValue={currentDocument?.status === 'published' ? '公開する' : '公開しない'}
                    fixRequestValue={fixRequestDocument?.status === 'published' ? '公開する' : '公開しない'}
                  />
                  
                  <SmartDiffValue
                    label="本文"
                    currentValue={currentDocument?.content}
                    fixRequestValue={fixRequestDocument?.content}
                    isMarkdown={true}
                  />
                </div>
              );
            })}
          </div>
        )}

        {/* データが空の場合 */}
        {allCategorySlugs.size === 0 && allDocumentSlugs.size === 0 && (
          <div className="text-center py-12">
            <div className="text-gray-400 text-lg">
              修正リクエストのデータがありません
            </div>
          </div>
        )}
      </div>
    </AdminLayout>
  );
} 