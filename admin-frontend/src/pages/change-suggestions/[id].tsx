import AdminLayout from '@/components/admin/layout';
import { useState, useEffect, useRef } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { useParams } from 'react-router-dom';
import { fetchPullRequestDetail, type PullRequestDetailResponse } from '@/api/pullRequest';
import { Settings } from '@/components/icon/common/Settings';
import { markdownToHtml } from '@/utils/markdownToHtml';
import React from 'react';
import { DocumentDetailed } from '@/components/icon/common/DocumentDetailed';
import { Folder } from '@/components/icon/common/Folder';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';

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
          <span className="textwh-300">{part}</span>
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
  const [showReviewerModal, setShowReviewerModal] = useState(false);
  const [reviewerSearch, setReviewerSearch] = useState('');
  const reviewerModalRef = useRef<HTMLDivElement | null>(null);
  const [users, setUsers] = useState<any[]>([]);
  const [loadingUsers, setLoadingUsers] = useState(false);
  const [selectedReviewers, setSelectedReviewers] = useState<number[]>([]);
  const [reviewersInitialized, setReviewersInitialized] = useState(false);
  const [initialReviewers, setInitialReviewers] = useState<number[]>([]);

  // ユーザー一覧を取得する関数
  const handleFetchUser = async (searchEmail?: string) => {
    setLoadingUsers(true);
    try {
      const endpoint = searchEmail
        ? `${API_CONFIG.ENDPOINTS.PULL_REQUEST_REVIEWERS.GET}?email=${encodeURIComponent(searchEmail)}`
        : API_CONFIG.ENDPOINTS.PULL_REQUEST_REVIEWERS.GET;

      const response = await apiClient.get(endpoint);
      setUsers(response.users || []);
    } catch (error) {
      console.error('ユーザー取得エラー:', error);
      setUsers([]);
    } finally {
      setLoadingUsers(false);
    }
  };

  // レビュアーモーダルが表示された時にユーザー一覧を取得
  useEffect(() => {
    if (showReviewerModal) {
      handleFetchUser();
      // モーダルを開いた時の初期状態を保存
      setInitialReviewers([...selectedReviewers]);
    }
  }, [showReviewerModal]);

  // レビュアー検索時の処理
  useEffect(() => {
    if (showReviewerModal && reviewerSearch) {
      const timeoutId = setTimeout(() => {
        handleFetchUser(reviewerSearch);
      }, 300);

      return () => clearTimeout(timeoutId);
    }
  }, [reviewerSearch, showReviewerModal]);

  // 既存のレビュアーをselectedReviewersに設定（一度だけ実行）
  useEffect(() => {
    if (pullRequestData?.reviewers && users.length > 0 && !reviewersInitialized) {
      const reviewerIds = users
        .filter(user => pullRequestData.reviewers.includes(user.email))
        .map(user => user.id);
      setSelectedReviewers(reviewerIds);
      setInitialReviewers(reviewerIds);
      setReviewersInitialized(true);
    }
  }, [pullRequestData?.reviewers, users, reviewersInitialized]);

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

  useEffect(() => {
    if (!showReviewerModal) return;
    const handleClickOutside = (event: MouseEvent) => {
      if (reviewerModalRef.current && !reviewerModalRef.current.contains(event.target as Node)) {
        setShowReviewerModal(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [showReviewerModal]);

  // レビュアーモーダルが閉じられた時のAPI実行
  useEffect(() => {
    if (showReviewerModal === false && reviewersInitialized) {
      // 初期状態と現在の状態を比較
      const arraysEqual = (a: number[], b: number[]) => {
        if (a.length !== b.length) return false;
        return a.sort().every((val, index) => val === b.sort()[index]);
      };

      if (!arraysEqual(initialReviewers, selectedReviewers)) {
        handleSetReviewers();
        setInitialReviewers(selectedReviewers);
      }
    }
  }, [showReviewerModal, reviewersInitialized, initialReviewers, selectedReviewers]);

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

  // レビュアー設定のハンドラー
  const handleSetReviewers = async () => {
    if (!id) return;

    try {
      const selectedEmails = selectedReviewers
        .map(reviewerId => {
          const user = users.find(u => u.id === reviewerId);
          return user?.email;
        })
        .filter(Boolean);

      const endpoint = API_CONFIG.ENDPOINTS.PULL_REQUEST_REVIEWERS.GET;
      await apiClient.post(endpoint, {
        pull_request_id: parseInt(id),
        emails: selectedEmails,
      });

      // 成功時はToast表示などの処理を追加可能
    } catch (error) {
      console.error('レビュアー設定エラー:', error);
      // エラー時の処理を追加可能
    }
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
      <div className="mb-20 w-full rounded-lg relative">
        {/* メインコンテンツエリア */}
        <div className="flex flex-1">
          {/* 左側: タイトルと本文 */}
          <div className="mb-6 relative w-full">
            {/* タイトル */}
            <div className="mb-6 max-w-3xl w-full">
              <label className="block text-white text-base font-medium mb-3">タイトル</label>
              <div className="w-full px-4 py-3 rounded-lg border border-gray-600 text-white">
                {pullRequestData.title}
              </div>
            </div>

            {/* 右側: レビュアー */}
            <div className="absolute right-0 top-0 flex flex-col items-start mr-20">
              <div className="flex items-center gap-40 relative" ref={reviewerModalRef}>
                <span className="text-white text-base font-bold">レビュアー</span>
                <Settings
                  className="w-5 h-5 text-gray-300 ml-2 cursor-pointer"
                  onClick={() => setShowReviewerModal(v => !v)}
                />
                {showReviewerModal && (
                  <div className="absolute left-0 top-full z-50 mt-2 w-full bg-[#181A1B] rounded-xl border border-gray-700 shadow-2xl">
                    <div className="flex flex-col">
                      <div className="px-5 pt-5 pb-2 border-b border-gray-700">
                        <div className="flex justify-between items-center mb-2">
                          <span className="text-white font-semibold text-base">
                            最大15人までリクエストできます
                          </span>
                        </div>
                        <input
                          type="text"
                          className="w-full px-3 py-2 rounded bg-[#222426] border border-gray-600 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"
                          placeholder="Type or choose a user"
                          value={reviewerSearch}
                          onChange={e => setReviewerSearch(e.target.value)}
                          autoFocus
                        />
                      </div>
                      {/* Suggestionsセクション */}
                      <div className="px-5 pt-3">
                        <div className="text-xs text-gray-400 font-semibold mb-2">Suggestions</div>
                        {loadingUsers ? (
                          <div className="text-gray-500 text-sm py-2">読み込み中...</div>
                        ) : users.length === 0 ? (
                          <div className="text-gray-500 text-sm py-2">ユーザーが見つかりません</div>
                        ) : (
                          users
                            .filter(user =>
                              user.email.toLowerCase().includes(reviewerSearch.toLowerCase())
                            )
                            .map(user => (
                              <div
                                key={user.id}
                                className={`flex items-center gap-3 px-2 py-2 rounded cursor-pointer hover:bg-[#23272d] ${selectedReviewers.includes(user.id) ? 'bg-[#23272d]' : ''}`}
                                onClick={() =>
                                  setSelectedReviewers(
                                    selectedReviewers.includes(user.id)
                                      ? selectedReviewers.filter(id => id !== user.id)
                                      : [...selectedReviewers, user.id]
                                  )
                                }
                              >
                                <span className="text-2xl">👤</span>
                                <div className="flex-1 min-w-0">
                                  <div className="text-white font-medium leading-tight">
                                    {user.email}
                                  </div>
                                  <div className="text-xs text-gray-400 truncate">
                                    {user.role || 'editor'}
                                  </div>
                                </div>
                              </div>
                            ))
                        )}
                      </div>
                    </div>
                  </div>
                )}
              </div>
              {selectedReviewers.length === 0 ? (
                <p className="text-white text-base font-medium mt-5 text-sm">レビュアーなし</p>
              ) : (
                <div className="mt-5">
                  <div className="space-y-1">
                    {selectedReviewers.map(reviewerId => {
                      const user = users.find(u => u.id === reviewerId);
                      return user ? (
                        <div key={reviewerId} className="flex items-center gap-2 text-sm">
                          <span className="text-xl">👤</span>
                          <span className="text-gray-300">{user.email}</span>
                        </div>
                      ) : null;
                    })}
                  </div>
                </div>
              )}
            </div>

            {/* 本文 */}
            <div className="mb-8 max-w-3xl w-full">
              <label className="block text-white text-base font-medium mb-3">本文</label>
              <div className="w-full px-4 py-3 rounded-lg border border-gray-600 text-white min-h-[120px]">
                {pullRequestData.description || '説明なし'}
              </div>
            </div>

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
                <div className="mb-8 mr-20">
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
              </div>
            )}
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
