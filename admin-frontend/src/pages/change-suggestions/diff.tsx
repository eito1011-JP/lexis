import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { useParams } from 'react-router-dom';
import {
  fetchPullRequestDetail,
  approvePullRequest,
  type PullRequestDetailResponse,
} from '@/api/pullRequest';
import { DocumentDetailed } from '@/components/icon/common/DocumentDetailed';
import { Folder } from '@/components/icon/common/Folder';
import { markdownStyles } from '@/styles/markdownContent';
import { PULL_REQUEST_STATUS } from '@/constants/pullRequestStatus';
import { mapBySlug } from '@/utils/diffUtils';
import { diffStyles } from '@/styles/diffStyles';
import { SmartDiffValue } from '@/components/diff/SmartDiffValue';
import { SlugBreadcrumb } from '@/components/diff/SlugBreadcrumb';
import { StatusBanner } from '@/components/diff/StatusBanner';
import { ConfirmationActionDropdown } from '@/components/diff/ConfirmationActionDropdown';
import type {
  DiffItem,
  DiffFieldInfo,
  DiffDataInfo,
  TabType,
  ConfirmationAction,
} from '@/types/diff';
import { TABS } from '@/types/diff';

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
  const [selectedConfirmationAction, setSelectedConfirmationAction] = useState<ConfirmationAction>(
    'create_correction_request'
  );

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
        console.log('data', data);
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
  const handleConfirmationAction = async () => {
    if (!id) return;

    switch (selectedConfirmationAction) {
      case 'create_correction_request':
        // 修正リクエスト作成画面に遷移
        window.location.href = `/admin/change-suggestions/${id}/fix-request`;
        break;
      case 're_edit_proposal':
        console.log('変更提案を再編集');
        // TODO: 変更提案の再編集画面への遷移
        break;
      case 'approve_changes':
        try {
          const result = await approvePullRequest(id);
          if (result.success) {
            // 承認成功時にアクティビティページに遷移
            window.location.href = `/admin/change-suggestions/${id}`;
          } else {
            setError(result.error || '変更の承認に失敗しました');
          }
        } catch (err) {
          console.error('承認エラー:', err);
          setError('変更の承認に失敗しました');
        }
        break;
    }
  };

  return (
    <AdminLayout title="変更内容詳細">
      <style>{markdownStyles}</style>
      <style>{diffStyles}</style>
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
