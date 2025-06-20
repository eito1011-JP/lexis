import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { Home } from '@/components/icon/common/Home';
import { Folder } from '@/components/icon/common/Folder';

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

type DiffResponse = {
  documents: Array<{
    original: DiffItem | null;
    current: DiffItem;
  }>;
  categories: Array<{
    original: DiffItem | null;
    current: DiffItem;
  }>;
};

// 差分表示コンポーネント
const DiffField = ({
  label,
  before,
  after,
}: {
  label: string;
  before?: string;
  after?: string;
}) => {
  // 変更がない場合
  if (before === after) {
    return (
      <div className="mb-4">
        <label className="block text-sm font-medium text-gray-300 mb-2">{label}</label>
        <div className="bg-gray-800 rounded-md p-3 text-sm text-gray-300">{after || '-'}</div>
      </div>
    );
  }

  return (
    <div className="mb-4">
      <label className="block text-sm font-medium text-gray-300 mb-2">{label}</label>
      <div className="space-y-2">
        {before && (
          <div className="bg-red-900/30 border border-red-800 rounded-md p-3 text-sm text-red-200">
            {before}
          </div>
        )}
        <div className="bg-green-900/30 border border-green-800 rounded-md p-3 text-sm text-green-200">
          {after || '（新規追加）'}
        </div>
      </div>
    </div>
  );
};

/**
 * 差分確認画面コンポーネント
 */
export default function DiffPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/login', false);

  const [diffData, setDiffData] = useState<DiffResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState<string | null>(null);

  useEffect(() => {
    const fetchDiff = async () => {
      try {
        // URLパラメータからuser_branch_idを取得
        const urlParams = new URLSearchParams(window.location.search);
        const userBranchId = urlParams.get('user_branch_id');

        if (!userBranchId) {
          setError('user_branch_idパラメータが必要です');
          setLoading(false);
          return;
        }

        const response = await apiClient.get(API_CONFIG.ENDPOINTS.GIT.GET_DIFF, {
          params: {
            user_branch_id: userBranchId,
          },
        });

        console.log('response', response);
        if (response) {
          setDiffData(response);
        }
      } catch (err) {
        console.error('差分取得エラー:', err);
        setError('差分データの取得に失敗しました');
      } finally {
        setLoading(false);
      }
    };

    fetchDiff();
  }, []);

  // PR作成のハンドラー
  const handleSubmitPR = async () => {
    setIsSubmitting(true);
    setSubmitError(null);
    setSubmitSuccess(null);

    try {
      const response = await apiClient.post(API_CONFIG.ENDPOINTS.GIT.CREATE_PR, {
        title: '更新内容の提出',
        description: 'このPRはハンドブックの更新を含みます。',
      });

      if (response.success) {
        setSubmitSuccess('差分の提出が完了しました');
        // 3秒後にドキュメント一覧に戻る
        setTimeout(() => {
          window.location.href = '/admin/documents';
        }, 3000);
      } else {
        setSubmitError(response.message || '差分の提出に失敗しました');
      }
    } catch (err) {
      console.error('差分提出エラー:', err);
      setSubmitError('差分の提出中にエラーが発生しました');
    } finally {
      setIsSubmitting(false);
    }
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

  // データ読み込み中
  if (loading) {
    return (
      <AdminLayout title="差分確認">
        <div className="flex flex-col items-center justify-center h-full">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
          <p className="text-gray-400">差分データを読み込み中...</p>
        </div>
      </AdminLayout>
    );
  }

  // エラー表示
  if (error) {
    return (
      <AdminLayout title="差分確認">
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
          <button
            className="px-4 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none"
            onClick={() => (window.location.href = '/admin/documents')}
          >
            ドキュメント一覧に戻る
          </button>
        </div>
      </AdminLayout>
    );
  }

  // データが空の場合
  if (!diffData || (diffData.categories.length === 0 && diffData.documents.length === 0)) {
    return (
      <AdminLayout title="差分確認">
        <div className="flex flex-col items-center justify-center h-full">
          <p className="text-gray-400 mb-4">変更された内容はありません</p>
          <button
            className="px-4 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none"
            onClick={() => (window.location.href = '/admin/documents')}
          >
            ドキュメント一覧に戻る
          </button>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout title="差分確認">
      <div className="flex flex-col h-full">
        {/* パンくずリスト */}
        <div className="flex items-center text-sm text-gray-400 mb-4">
          <a href="/admin" className="hover:text-white">
            <Home className="w-4 h-4 mx-2" />
          </a>
          <span className="mx-2">/</span>
          <a href="/admin/documents" className="hover:text-white">
            ドキュメント管理
          </a>
          <span className="mx-2">/</span>
          <span className="text-white">差分確認</span>
        </div>

        {/* 成功メッセージ */}
        {submitSuccess && (
          <div className="mb-4 p-3 bg-green-900/50 border border-green-800 rounded-md text-green-200">
            <div className="flex items-center">
              <svg
                className="w-5 h-5 mr-2 text-green-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M5 13l4 4L19 7"
                />
              </svg>
              <span>{submitSuccess}</span>
            </div>
            <p className="text-sm mt-2">3秒後にドキュメント一覧に戻ります...</p>
          </div>
        )}

        {/* エラーメッセージ */}
        {submitError && (
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
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
              <span>{submitError}</span>
            </div>
          </div>
        )}

        {/* ヘッダー */}
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-2xl font-bold">作業内容の確認</h1>
          <div className="flex gap-2">
            <button
              className="px-4 py-2 bg-red-700 rounded-md hover:bg-red-600 focus:outline-none text-white"
              onClick={() => (window.location.href = '/admin/documents')}
              disabled={isSubmitting}
            >
              戻る
            </button>
            <button
              className="px-4 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none flex items-center text-white"
              onClick={handleSubmitPR}
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mr-2"></div>
                  <span>提出中...</span>
                </>
              ) : (
                <span>差分を提出する</span>
              )}
            </button>
          </div>
        </div>

        {/* カテゴリの変更数表示 */}
        <div className="mb-6">
          <div className="text-sm text-gray-400">カテゴリの変更 × {diffData.categories.length}</div>

          {/* Slug一覧 */}
          <div className="mt-2 space-y-1">
            {diffData.categories.map(category => (
              <div key={category.current.id} className="text-sm">
                <div className="bg-red-900/30 border border-red-800 rounded-md px-3 py-1 text-red-200 inline-block mr-2">
                  {category.original?.slug || 'this-is-sample-slug'}
                </div>
                <div className="bg-green-900/30 border border-green-800 rounded-md px-3 py-1 text-green-200 inline-block">
                  {category.current.slug || 'this-is-new-sample-slug'}
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* 変更されたカテゴリの詳細 */}
        {diffData.categories.length > 0 && (
          <div className="mb-8">
            <h2 className="text-xl font-bold mb-4 flex items-center">
              <Folder className="w-5 h-5 mr-2" />
              カテゴリ名
            </h2>
            <div className="space-y-4">
              {diffData.categories.map(category => (
                <div
                  key={category.current.id}
                  className="bg-gray-900 rounded-lg border border-gray-800 p-6"
                >
                  <DiffField
                    label="Slug"
                    before={category.original?.slug}
                    after={category.current.slug}
                  />
                  <DiffField
                    label="カテゴリ名"
                    before={category.original?.sidebar_label}
                    after={category.current.sidebar_label}
                  />
                  <DiffField
                    label="表示順"
                    before={category.original?.position?.toString()}
                    after={category.current.position?.toString()}
                  />
                  <DiffField
                    label="説明"
                    before={category.original?.description}
                    after={category.current.description}
                  />
                </div>
              ))}
            </div>
          </div>
        )}

        {/* ドキュメントの変更数表示 */}
        <div className="mb-6">
          <div className="text-sm text-gray-400">
            ドキュメントの変更 × {diffData.documents.length}
          </div>
        </div>

        {/* 変更されたドキュメントの詳細 */}
        {diffData.documents.length > 0 && (
          <div className="mb-8">
            <div className="space-y-6">
              {diffData.documents.map(document => (
                <div
                  key={document.current.id}
                  className="bg-gray-900 rounded-lg border border-gray-800 p-6"
                >
                  <DiffField
                    label="Slug"
                    before={document.original?.slug}
                    after={document.current.slug}
                  />
                  <DiffField
                    label="タイトル"
                    before={document.original?.title}
                    after={document.current.title}
                  />
                  <DiffField
                    label="公開設定"
                    before={document.original ? '公開する' : undefined}
                    after={document.current ? '公開しない' : '公開する'}
                  />
                  <div className="mb-4">
                    <label className="block text-sm font-medium text-gray-300 mb-2">本文</label>
                    <div className="space-y-2">
                      {document.original?.content && (
                        <div className="bg-red-900/30 border border-red-800 rounded-md p-3 text-sm text-red-200">
                          {document.original.content}
                        </div>
                      )}
                      <div className="bg-green-900/30 border border-green-800 rounded-md p-3 text-sm text-green-200">
                        {document.current.content || '（新規追加）'}
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
