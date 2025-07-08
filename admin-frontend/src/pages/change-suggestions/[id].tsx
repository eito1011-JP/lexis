import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { useParams } from 'react-router-dom';
import { fetchPullRequestDetail, type PullRequestDetailResponse } from '@/api/pullRequest';
import { Settings } from '@/components/icon/common/Settings';
import { markdownToHtml } from '@/utils/markdownToHtml';
import React from 'react';

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

type DiffFieldInfo = {
  status: 'added' | 'deleted' | 'modified' | 'unchanged';
  current: any;
  original: any;
};

type DiffDataInfo = {
  id: number;
  type: 'document' | 'category';
  operation: 'created' | 'updated' | 'deleted';
  changed_fields: Record<string, DiffFieldInfo>;
};

// SmartDiffValueコンポーネント
const SmartDiffValue: React.FC<{
  label: string;
  fieldInfo: DiffFieldInfo;
  isMarkdown?: boolean;
}> = ({ label, fieldInfo, isMarkdown = false }) => {
  const renderValue = (value: any) => {
    if (value === null || value === undefined) return '(なし)';
    if (typeof value === 'boolean') return value ? 'はい' : 'いいえ';
    return String(value);
  };

  const renderContent = (content: string, isMarkdown: boolean) => {
    if (!isMarkdown) return content;

    try {
      const htmlContent = markdownToHtml(content);
      return <div dangerouslySetInnerHTML={{ __html: htmlContent }} />;
    } catch (error) {
      return content;
    }
  };

  return (
    <div className="mb-4">
      <label className="block text-sm font-medium text-gray-300 mb-2">{label}</label>

      {fieldInfo.status === 'added' && (
        <div className="bg-green-800 rounded-md p-3 text-sm text-white">
          {renderContent(renderValue(fieldInfo.current), isMarkdown)}
        </div>
      )}

      {fieldInfo.status === 'deleted' && (
        <div className="bg-red-800 rounded-md p-3 text-sm text-white">
          {renderContent(renderValue(fieldInfo.original), isMarkdown)}
        </div>
      )}

      {fieldInfo.status === 'modified' && (
        <div className="space-y-1">
          <div className="bg-red-800 rounded-md p-3 text-sm text-white">
            {renderContent(renderValue(fieldInfo.original), isMarkdown)}
          </div>
          <div className="bg-green-800 rounded-md p-3 text-sm text-white">
            {renderContent(renderValue(fieldInfo.current), isMarkdown)}
          </div>
        </div>
      )}

      {fieldInfo.status === 'unchanged' && (
        <div className="bg-gray-800 border border-gray-600 rounded-md p-3 text-sm text-gray-300">
          {renderContent(renderValue(fieldInfo.current || fieldInfo.original), isMarkdown)}
        </div>
      )}
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
          <span className="text-blue-400">{part}</span>
          {index < parts.length - 1 && <span>/</span>}
        </span>
      ))}
    </div>
  );
};

export default function ChangeSuggestionDetailPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/login', false);
  const { id } = useParams<{ id: string }>();

  const [pullRequestData, setPullRequestData] = useState<PullRequestDetailResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchData = async () => {
      if (!id) {
        setError('プルリクエストIDが指定されていません');
        setLoading(false);
        return;
      }

      try {
        setLoading(true);
        const data = await fetchPullRequestDetail(id);
        setPullRequestData(data);
      } catch (err) {
        console.error('プルリクエスト詳細取得エラー:', err);
        setError('プルリクエスト詳細の取得に失敗しました');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [id]);

  // 差分データをIDでマップ化する関数
  const getDiffInfoById = (id: number, type: 'document' | 'category'): DiffDataInfo | null => {
    if (!pullRequestData?.diff_data) return null;
    return (
      pullRequestData.diff_data.find(
        (diff: DiffDataInfo) => diff.id === id && diff.type === type
      ) || null
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

  // 戻るボタンのハンドラー
  const handleGoBack = () => {
    window.location.href = '/change-suggestions';
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
      <AdminLayout title="変更提案詳細">
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

  if (!pullRequestData) {
    return (
      <AdminLayout title="変更提案詳細">
        <div className="flex flex-col items-center justify-center h-full">
          <p className="text-gray-400">データが見つかりません</p>
        </div>
      </AdminLayout>
    );
  }

  const originalDocs = mapBySlug(pullRequestData.original_document_versions || []);
  const originalCats = mapBySlug(pullRequestData.original_document_categories || []);

  return (
    <AdminLayout title="作業内容の確認">
      <div className="flex flex-col h-full">
        {/* メインコンテンツエリア */}
        <div className="flex flex-1">
          {/* 左側: タイトルと本文 */}
          <div className="flex-1 pr-6">
            {/* タイトル */}
            <div className="mb-6">
              <label className="block text-sm font-medium text-white mb-2">タイトル</label>
              <div className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white">
                {pullRequestData.title}
              </div>
            </div>

            {/* 本文 */}
            <div className="mb-8">
              <label className="block text-sm font-medium text-white mb-2">本文</label>
              <div className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white min-h-[120px]">
                {pullRequestData.description || '説明なし'}
              </div>
            </div>

            {/* カテゴリの変更 */}
            {pullRequestData.document_categories.length > 0 && (
              <div className="mb-8">
                <h3 className="text-white font-bold mb-4">
                  カテゴリの変更 × {pullRequestData.document_categories.length}
                </h3>
                <div className="space-y-4">
                  {pullRequestData.document_categories.map((category: DiffItem) => {
                    const diffInfo = getDiffInfoById(category.id, 'category');
                    const originalCategory = originalCats[category.slug];

                    return (
                      <div
                        key={category.id}
                        className="bg-gray-900 rounded-lg border border-gray-700 p-4"
                      >
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
                          label="カテゴリ名"
                          fieldInfo={getFieldInfo(
                            diffInfo,
                            'sidebar_label',
                            category.sidebar_label,
                            originalCategory?.sidebar_label
                          )}
                        />
                        <SmartDiffValue
                          label="表示順"
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

            {/* ドキュメントの変更 */}
            {pullRequestData.document_versions.length > 0 && (
              <div className="mb-8">
                <h3 className="text-white font-bold mb-4">
                  ドキュメントの変更 × {pullRequestData.document_versions.length}
                </h3>
                <div className="space-y-6">
                  {pullRequestData.document_versions.map((document: DiffItem) => {
                    const diffInfo = getDiffInfoById(document.id, 'document');
                    const originalDocument = originalDocs[document.slug];

                    return (
                      <div
                        key={document.id}
                        className="bg-gray-900 rounded-lg border border-gray-700 p-4"
                      >
                        <SlugBreadcrumb slug={document.slug} />
                        <SmartDiffValue
                          label="Slug"
                          fieldInfo={getFieldInfo(
                            diffInfo,
                            'slug',
                            document.slug,
                            originalDocument?.slug
                          )}
                        />
                        <SmartDiffValue
                          label="タイトル"
                          fieldInfo={getFieldInfo(
                            diffInfo,
                            'sidebar_label',
                            document.sidebar_label,
                            originalDocument?.sidebar_label
                          )}
                        />
                        <SmartDiffValue
                          label="公開設定"
                          fieldInfo={getFieldInfo(
                            diffInfo,
                            'is_public',
                            document.status === 'published' ? '公開する' : '公開しない',
                            originalDocument?.status === 'published' ? '公開する' : '公開しない'
                          )}
                        />
                        <SmartDiffValue
                          label="本文"
                          fieldInfo={getFieldInfo(
                            diffInfo,
                            'content',
                            document.content,
                            originalDocument?.content
                          )}
                          isMarkdown
                        />
                      </div>
                    );
                  })}
                </div>
              </div>
            )}
          </div>

          {/* 右側: レビュアー */}
          <div className="w-80">
            <div className="bg-gray-900 rounded-lg border border-gray-700 p-4">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-white font-bold">レビュアー</h3>
                <Settings className="w-4 h-4 text-gray-400" />
              </div>

              <div className="text-sm text-gray-400 mb-4">レビュアーなし</div>

              {pullRequestData.reviewers.length > 0 && (
                <div className="space-y-2">
                  {pullRequestData.reviewers.map((reviewer, index) => (
                    <div key={index} className="flex items-center text-white text-sm">
                      <span className="w-6 h-6 bg-gray-600 rounded-full mr-2 flex items-center justify-center text-xs">
                        {reviewer.charAt(0).toUpperCase()}
                      </span>
                      {reviewer}
                    </div>
                  ))}
                </div>
              )}

              <div className="mt-4 p-3 bg-green-800 rounded-md">
                <button className="w-full text-white text-sm font-medium bg-green-700 hover:bg-green-600 px-4 py-2 rounded-md transition-colors">
                  確認アクション ▼
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* 下部のボタン */}
        <div className="flex justify-center gap-4 mt-8 pb-6">
          <button
            onClick={handleGoBack}
            className="px-8 py-3 bg-red-600 hover:bg-red-700 text-white font-medium rounded-md transition-colors"
          >
            戻る
          </button>
          <button
            className="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors"
            disabled
          >
            変更を反映する
          </button>
        </div>
      </div>
    </AdminLayout>
  );
}
