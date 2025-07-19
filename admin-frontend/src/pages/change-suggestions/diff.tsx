import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { useParams } from 'react-router-dom';
import { fetchPullRequestDetail, type PullRequestDetailResponse } from '@/api/pullRequest';
import { markdownToHtml } from '@/utils/markdownToHtml';
import React from 'react';
import { DocumentDetailed } from '@/components/icon/common/DocumentDetailed';
import { Folder } from '@/components/icon/common/Folder';
import { markdownStyles } from '@/styles/markdownContent';
import { formatDistanceToNow } from 'date-fns';
import ja from 'date-fns/locale/ja';
import { PULL_REQUEST_STATUS } from '@/constants/pullRequestStatus';
import { Merge } from '@/components/icon/common/Merge';
import { Merged } from '@/components/icon/common/Merged';
import { Closed } from '@/components/icon/common/Closed';
import { ChevronDown } from '@/components/icon/common/ChevronDown';

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

// タブ定義
type TabType = 'activity' | 'changes';

const TABS = [
  { id: 'activity' as TabType, label: 'アクティビティ', icon: '💬' },
  { id: 'changes' as TabType, label: '変更内容', icon: '📝' },
] as const;

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

  return (
    <div className="mb-4">
      <label className="block text-sm font-medium text-gray-300 mb-2">{label}</label>

      {fieldInfo.status === 'added' && (
        <div className="bg-green-900/30 border border-green-700 rounded-md p-3 text-sm text-green-200">
          {renderContent(renderValue(fieldInfo.current), isMarkdown)}
        </div>
      )}

      {fieldInfo.status === 'deleted' && (
        <div className="bg-red-900/30 border border-red-700 rounded-md p-3 text-sm text-red-200">
          {renderContent(renderValue(fieldInfo.original), isMarkdown)}
        </div>
      )}

      {fieldInfo.status === 'modified' && (
        <div className="space-y-1">
          <div className="bg-red-900/30 border border-red-700 rounded-md p-3 text-sm text-red-200">
            {renderContent(renderValue(fieldInfo.original), isMarkdown)}
          </div>
          <div className="bg-green-900/30 border border-green-700 rounded-md p-3 text-sm text-green-200">
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
          <span className="text-gray-300">{part}</span>
          {index < parts.length - 1 && <span>/</span>}
        </span>
      ))}
    </div>
  );
};

// ステータスバナーコンポーネント
const StatusBanner: React.FC<{
  status: string;
  authorEmail: string;
  createdAt: string;
  conflict: boolean;
  title: string;
}> = ({ status, authorEmail, createdAt, conflict, title }) => {
  let button;
  switch (true) {
    case conflict:
      button = (
        <button
          type="button"
          className="flex items-center px-7 py-3 rounded-full bg-[#DA3633] focus:outline-none"
          disabled
        >
          <Closed className="w-5 h-5 mr-2" />
          <span className="text-white text-md font-bold">コンフリクト</span>
        </button>
      );
      break;
    case status === PULL_REQUEST_STATUS.MERGED:
      button = (
        <button
          type="button"
          className="flex items-center px-7 py-3 rounded-full bg-[#3832A5] focus:outline-none"
          disabled
        >
          <Merged className="w-5 h-5 mr-2" />
          <span className="text-white text-md font-bold">反映済み</span>
        </button>
      );
      break;
    case status === PULL_REQUEST_STATUS.OPENED:
      button = (
        <button
          type="button"
          className="flex items-center px-7 py-3 rounded-full bg-[#1B6E2A] focus:outline-none"
          disabled
        >
          <Merge className="w-5 h-5 mr-2" />
          <span className="text-white text-md font-bold">未対応</span>
        </button>
      );
      break;
    case status === PULL_REQUEST_STATUS.CLOSED:
      button = (
        <button
          type="button"
          className="flex items-center px-7 py-3 rounded-full bg-[#DA3633] focus:outline-none"
          disabled
        >
          <Closed className="w-5 h-5 mr-2" />
          <span className="text-white text-md font-bold">取り下げ</span>
        </button>
      );
      break;
    default:
      button = null;
  }
  return (
    <div className={`mb-10 rounded-lg`}>
      {/* タイトル表示 */}
      <h1 className="text-3xl font-bold text-white mb-4">{title}</h1>
      <div className="flex items-center justify-start">
        {button}
        <span className="font-medium text-[#B1B1B1] ml-4">
          {authorEmail}さんが{' '}
          {formatDistanceToNow(new Date(createdAt), { addSuffix: true, locale: ja })}{' '}
          に変更を提出しました
        </span>
      </div>
    </div>
  );
};

// 確認アクションの型定義
type ConfirmationAction = 'create_correction_request' | 're_edit_proposal' | 'approve_changes';

// ConfirmationActionDropdownコンポーネント
const ConfirmationActionDropdown: React.FC<{
  selectedAction: ConfirmationAction;
  onActionChange: (action: ConfirmationAction) => void;
  onConfirm: () => void;
}> = ({ selectedAction, onActionChange, onConfirm }) => {
  const [isOpen, setIsOpen] = useState(false);

  const actions = [
    {
      value: 'create_correction_request' as ConfirmationAction,
      label: '修正リクエストを作成',
    },
    {
      value: 're_edit_proposal' as ConfirmationAction,
      label: '変更提案を再編集する',
    },
    {
      value: 'approve_changes' as ConfirmationAction,
      label: '変更を承認する',
    },
  ];

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center px-4 py-2 bg-gray-800 border border-gray-600 rounded-md text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <span>確認アクション</span>
        <ChevronDown className="w-4 h-4 ml-2" />
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-2 w-64 bg-gray-800 border border-gray-600 rounded-md shadow-lg z-10">
          <div className="p-4">
            <div className="space-y-3">
              {actions.map((action) => (
                <label key={action.value} className="flex items-center cursor-pointer">
                  <input
                    type="radio"
                    name="confirmationAction"
                    value={action.value}
                    checked={selectedAction === action.value}
                    onChange={() => onActionChange(action.value)}
                    className="mr-3 text-blue-500 focus:ring-blue-500"
                  />
                  <span className="text-white text-sm">{action.label}</span>
                </label>
              ))}
            </div>
            <div className="mt-4 flex justify-end">
              <button
                type="button"
                onClick={() => {
                  onConfirm();
                  setIsOpen(false);
                }}
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                確定する
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default function ChangeSuggestionDiffPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/login', false);
  const { id } = useParams<{ id: string }>();

  const [pullRequestData, setPullRequestData] = useState<PullRequestDetailResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<TabType>('changes');
  const [conflictStatus, setConflictStatus] = useState<{
    mergeable: boolean | null;
    mergeable_state: string | null;
  }>({ mergeable: null, mergeable_state: null });
  const [selectedConfirmationAction, setSelectedConfirmationAction] = useState<ConfirmationAction>('create_correction_request');

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
      <AdminLayout title="変更内容詳細">
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
      <AdminLayout title="変更内容詳細">
        <div className="flex flex-col items-center justify-center h-full">
          <p className="text-gray-400">データが見つかりません</p>
        </div>
      </AdminLayout>
    );
  }

  // 確認アクションの処理
  const handleConfirmationAction = () => {
    switch (selectedConfirmationAction) {
      case 'create_correction_request':
        console.log('修正リクエストを作成');
        // TODO: 修正リクエスト作成のAPI呼び出し
        break;
      case 're_edit_proposal':
        console.log('変更提案を再編集');
        // TODO: 変更提案の再編集画面への遷移
        break;
      case 'approve_changes':
        console.log('変更を承認');
        // TODO: 変更承認のAPI呼び出し
        break;
    }
  };

  return (
    <AdminLayout title="変更内容詳細">
      <style>{markdownStyles}</style>
      <div className="mb-20 w-full rounded-lg relative">
        {/* ステータスバナー */}
        {(pullRequestData.status === PULL_REQUEST_STATUS.MERGED ||
          pullRequestData.status === PULL_REQUEST_STATUS.OPENED ||
          pullRequestData.status === PULL_REQUEST_STATUS.CLOSED ||
          conflictStatus.mergeable === false) && (
          <StatusBanner
            status={pullRequestData.status}
            authorEmail={pullRequestData.author_email}
            createdAt={pullRequestData.created_at}
            conflict={conflictStatus.mergeable === false}
            title={pullRequestData.title}
          />
        )}
        
        {/* 確認アクションボタン */}
        <div className="flex justify-end mb-6">
          <ConfirmationActionDropdown
            selectedAction={selectedConfirmationAction}
            onActionChange={setSelectedConfirmationAction}
            onConfirm={handleConfirmationAction}
          />
        </div>

        {/* タブナビゲーション */}
        <div className="mb-8">
          <nav className="flex">
            {TABS.map(tab => (
              <button
                key={tab.id}
                onClick={() => {
                  if (tab.id === 'activity') {
                    window.location.href = `/admin/change-suggestions/${id}`;
                  } else {
                    setActiveTab(tab.id);
                  }
                }}
                className={`py-2 px-4 font-medium text-sm transition-colors ${
                  activeTab === tab.id
                    ? 'text-white border border-white border-b-0 rounded-t-lg'
                    : 'text-white hover:text-gray-300 hover:bg-gray-800 border-b border-white'
                }`}
              >
                <span className="mr-2">{tab.icon}</span>
                {tab.label}
              </button>
            ))}
          </nav>

          {/* タブ下の長い水平線 */}
          <div className="w-full h-px bg-white mt-0"></div>
        </div>

        {/* 変更内容タブ */}
        {pullRequestData && (
          <>
            {(() => {
              const originalDocs = mapBySlug(pullRequestData.original_document_versions || []);
              const originalCats = mapBySlug(pullRequestData.original_document_categories || []);

              return (
                <>
                  {/* カテゴリの変更 */}
                  {pullRequestData.document_categories.length > 0 && (
                    <div className="mb-10">
                      <h2 className="text-xl font-bold mb-4 flex items-center">
                        <Folder className="w-5 h-5 mr-2" />
                        カテゴリの変更 × {pullRequestData.document_categories.length}
                      </h2>
                      <div className="space-y-4">
                        {pullRequestData.document_categories.map((category: DiffItem) => {
                          const diffInfo = getDiffInfoById(category.id, 'category');
                          const originalCategory = originalCats[category.slug];

                          return (
                            <div
                              key={category.id}
                              className="bg-gray-900 rounded-lg border border-gray-800 p-6"
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
                    <div>
                      <h2 className="text-xl font-bold mb-4 flex items-center">
                        <DocumentDetailed className="w-6 h-6 mr-2" />
                        ドキュメントの変更 × {pullRequestData.document_versions.length}
                      </h2>
                      <div className="space-y-6">
                        {pullRequestData.document_versions.map((document: DiffItem) => {
                          const diffInfo = getDiffInfoById(document.id, 'document');
                          const originalDocument = originalDocs[document.slug];

                          return (
                            <div
                              key={document.id}
                              className="bg-gray-900 rounded-lg border border-gray-800 p-6"
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
                                  originalDocument?.status === 'published'
                                    ? '公開する'
                                    : '公開しない'
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
                </>
              );
            })()}
          </>
        )}
      </div>
    </AdminLayout>
  );
}
