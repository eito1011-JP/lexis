import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { Toast } from '@/components/admin/Toast';
import { SmartDiffValue } from '@/components/diff/SmartDiffValue';
import { SlugBreadcrumb } from '@/components/diff/SlugBreadcrumb';
import { markdownStyles } from '@/styles/markdownContent';
import { diffStyles } from '@/styles/diffStyles';
import type { DiffFieldInfo, DiffDataInfo } from '@/types/diff';

// 差分データの型定義
type DiffItem = {
  id: number;
  slug: string;
  sidebar_label: string;
  description?: string;
  title?: string;
  content?: string;
  is_public?: boolean;
  position?: number;
  file_order?: number;
  parent_id?: number;
  category_id?: number;
  status: string;
  user_branch_id: number;
  created_at: string;
  updated_at: string;
};



export default function PullRequestEditSessionDetailPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/login', false);
  const { token } = useParams<{ token: string }>();

  const [diffData, setDiffData] = useState<{
    document_versions: DiffItem[];
    document_categories: DiffItem[];
    original_document_versions: DiffItem[];
    original_document_categories: DiffItem[];
    diff_data: DiffDataInfo[];
  } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  // 差分データをIDでマップ化する関数
  const getDiffInfoById = (id: number, type: 'document' | 'category'): DiffDataInfo | null => {
    if (!diffData?.diff_data) return null;
    return (
      diffData.diff_data.find((diff: DiffDataInfo) => diff.id === id && diff.type === type) || null
    );
  };

  // フィールド情報を取得する関数
  const getFieldInfo = (
    diffInfo: DiffDataInfo | null,
    fieldName: string,
    currentValue: any,
    originalValue?: any
  ): DiffFieldInfo => {
    if (!diffInfo) {
      return {
        status: 'unchanged',
        current: currentValue,
        original: originalValue,
      };
    }

    if (diffInfo.operation === 'deleted') {
      return {
        status: 'deleted',
        current: null,
        original: originalValue,
      };
    }

    if (!diffInfo.changed_fields[fieldName]) {
      return {
        status: 'unchanged',
        current: currentValue,
        original: originalValue,
      };
    }
    return diffInfo.changed_fields[fieldName];
  };

  // データをslugでマップ化する関数
  const mapBySlug = (items: DiffItem[]) => {
    return items.reduce(
      (acc, item) => {
        acc[item.slug] = item;
        return acc;
      },
      {} as Record<string, DiffItem>
    );
  };

  useEffect(() => {
    const fetchEditDiff = async () => {
      if (!token) {
        setError('トークンが指定されていません');
        setLoading(false);
        return;
      }

      try {
        setLoading(true);
        const response = await apiClient.get(
          `${API_CONFIG.ENDPOINTS.PULL_REQUEST_EDIT_SESSIONS.GET}?token=${token}`
        );
        console.log('response', response);
        setDiffData(response);
      } catch (err: any) {
        console.error('編集差分取得エラー:', err);
        setError('編集差分の取得に失敗しました');
        setToast({
          message: '編集差分の取得に失敗しました',
          type: 'error',
        });
      } finally {
        setLoading(false);
      }
    };

    fetchEditDiff();
  }, [token]);

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
      <AdminLayout title="変更提案編集詳細">
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
      <AdminLayout title="変更提案編集詳細">
        <div className="flex flex-col items-center justify-center h-full">
          <p className="text-gray-400">データが見つかりません</p>
        </div>
      </AdminLayout>
    );
  }

  // ドキュメントとカテゴリのマップを作成
  const currentDocumentsMap = mapBySlug(diffData.document_versions);
  const currentCategoriesMap = mapBySlug(diffData.document_categories);
  const originalDocumentsMap = mapBySlug(diffData.original_document_versions);
  const originalCategoriesMap = mapBySlug(diffData.original_document_categories);

  return (
    <AdminLayout title="変更提案編集詳細">
      <style>{markdownStyles}</style>
      <style>{diffStyles}</style>
      {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}

      <div className="mb-20 w-full rounded-lg relative">
        <div className="mb-10">
          <h1 className="text-3xl font-bold text-white mb-4">変更提案編集詳細</h1>
          <p className="text-gray-400">この変更提案の編集内容を確認できます。</p>
        </div>

        {/* ドキュメントの変更 */}
        {diffData.document_versions.length > 0 && (
          <div className="mb-8">
            <h2 className="text-xl font-semibold text-white mb-4">📝 ドキュメントの変更</h2>
            <div className="space-y-6">
              {diffData.document_versions.map((doc, index) => {
                const diffInfo = getDiffInfoById(doc.id, 'document');
                const originalDoc = originalDocumentsMap[doc.slug];

                return (
                  <div key={index} className="border border-gray-600 rounded-lg p-6">
                    <SlugBreadcrumb slug={doc.slug} />

                    <SmartDiffValue
                      label="Slug"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'slug',
                        doc.slug,
                        originalDoc?.slug
                      )}
                    />

                    <SmartDiffValue
                      label="表示順序"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'file_order',
                        doc.file_order,
                        originalDoc?.file_order
                      )}
                    />

                    <SmartDiffValue
                      label="タイトル"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'sidebar_label',
                        doc.sidebar_label,
                        originalDoc?.sidebar_label
                      )}
                    />

                    <SmartDiffValue
                      label="公開設定"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'is_public',
                        doc.is_public,
                        originalDoc?.is_public
                      )}
                    />

                    <SmartDiffValue
                      label="本文"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'content',
                        doc.content,
                        originalDoc?.content
                      )}
                      isMarkdown
                    />
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* カテゴリの変更 */}
        {diffData.document_categories.length > 0 && (
          <div className="mb-8">
            <h2 className="text-xl font-semibold text-white mb-4">📁 カテゴリの変更</h2>
            <div className="space-y-6">
              {diffData.document_categories.map((category, index) => {
                const diffInfo = getDiffInfoById(category.id, 'category');
                const originalCategory = originalCategoriesMap[category.slug];

                return (
                  <div key={index} className="border border-gray-600 rounded-lg p-6">
                    <SlugBreadcrumb slug={category.slug} />

                    <SmartDiffValue
                      label="Slug"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'slug',
                        category.slug,
                        originalCategory?.slug
                      )}
                    />

                    <SmartDiffValue
                      label="タイトル"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'sidebar_label',
                        category.sidebar_label,
                        originalCategory?.sidebar_label
                      )}
                    />  

                    <SmartDiffValue
                      label="表示順序"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'position',
                        category.position,
                        originalCategory?.position
                      )}
                    />

                    <SmartDiffValue
                      label="説明"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'description',
                        category.description,
                        originalCategory?.description
                      )}
                    />
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {diffData.document_versions.length === 0 && diffData.document_categories.length === 0 && (
          <div className="text-center py-8">
            <p className="text-gray-400">変更内容がありません。</p>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
