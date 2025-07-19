import AdminLayout from '@/components/admin/layout';
import { useState, useEffect, useRef, useCallback } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { useParams } from 'react-router-dom';
import {
  fetchPullRequestDetail,
  fetchActivityLog,
  type PullRequestDetailResponse,
  type Reviewer,
  type ActivityLog,
} from '@/api/pullRequest';
import { Settings } from '@/components/icon/common/Settings';
import React from 'react';

import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { Toast } from '@/components/admin/Toast';
import { Merge } from '@/components/icon/common/Merge';
import { Merged } from '@/components/icon/common/Merged';
import { Closed } from '@/components/icon/common/Closed';
import { formatDistanceToNow } from 'date-fns';
import ja from 'date-fns/locale/ja';
import { PULL_REQUEST_STATUS } from '@/constants/pullRequestStatus';
import { markdownToHtml } from '@/utils/markdownToHtml';
import { markdownStyles } from '@/styles/markdownContent';

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

// ユーザーオブジェクトの型定義
type User = {
  id: number;
  email: string;
  role?: string;
  created_at?: string;
};

// コメントの型定義
type Comment = {
  id: number;
  author: string | null;
  content: string;
  is_resolved: boolean;
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

// タブ定義
type TabType = 'activity' | 'changes';

const TABS = [
  { id: 'activity' as TabType, label: 'アクティビティ', icon: '💬' },
  { id: 'changes' as TabType, label: '変更内容', icon: '📝' },
] as const;

// ActivityLogItemコンポーネント
const ActivityLogItem: React.FC<{ log: ActivityLog }> = ({ log }) => {
  const getActionDisplayName = (action: string): string => {
    switch (action) {
      case 'fix_request_sent':
        return '修正リクエストが送信されました';
      case 'assigned_reviewer':
        return 'レビュワーが設定されました';
      case 'reviewer_approved':
        return '変更提案が承認されました';
      case 'commented':
        return 'コメントが投稿されました';
      case 'pull_request_merged':
        return 'プルリクエストがマージされました';
      case 'pull_request_closed':
        return 'プルリクエストがクローズされました';
      case 'pull_request_reopened':
        return 'プルリクエストが再オープンされました';
      case 'pull_request_edited':
        return 'プルリクエストが編集されました';
      case 'pull_request_title_edited':
        return 'タイトルが編集されました';
      default:
        return 'アクション';
    }
  };

  const getActionIcon = (action: string): string => {
    switch (action) {
      case 'fix_request_sent':
        return '🔧';
      case 'assigned_reviewer':
        return '👥';
      case 'reviewer_approved':
        return '✅';
      case 'commented':
        return '💬';
      case 'pull_request_merged':
        return '🔀';
      case 'pull_request_closed':
        return '❌';
      case 'pull_request_reopened':
        return '🔄';
      case 'pull_request_edited':
        return '✏️';
      case 'pull_request_title_edited':
        return '📝';
      default:
        return '📋';
    }
  };

  const getActionColor = (action: string): string => {
    switch (action) {
      case 'fix_request_sent':
        return 'bg-red-600';
      case 'assigned_reviewer':
        return 'bg-blue-600';
      case 'reviewer_approved':
        return 'bg-green-600';
      case 'commented':
        return 'bg-purple-600';
      case 'pull_request_merged':
        return 'bg-purple-600';
      case 'pull_request_closed':
        return 'bg-red-600';
      case 'pull_request_reopened':
        return 'bg-orange-600';
      case 'pull_request_edited':
        return 'bg-yellow-600';
      case 'pull_request_title_edited':
        return 'bg-yellow-600';
      default:
        return 'bg-gray-600';
    }
  };

  return (
    <div className="timeline-item">
      <div className={`timeline-avatar ${getActionColor(log.action)}`}>
        <span className="text-white text-sm">{getActionIcon(log.action)}</span>
      </div>
      <div className="timeline-content timeline-content-with-line">
        <div className="text-red-300 text-sm mb-1 ml-[-0.7rem]">
          {log.actor?.name || 'システム'}さんが{getActionDisplayName(log.action)}
        </div>

        {/* タイトル編集の場合の詳細表示 */}
        {log.action === 'pull_request_title_edited' &&
          log.old_pull_request_title &&
          log.new_pull_request_title && (
            <div className="ml-[-0.7rem] mt-2">
              <div className="text-xs text-gray-400 mb-1">変更前:</div>
              <div className="text-sm text-red-200 mb-2 bg-red-900/30 border border-red-700 rounded p-2">
                {log.old_pull_request_title}
              </div>
              <div className="text-xs text-gray-400 mb-1">変更後:</div>
              <div className="text-sm text-green-200 bg-green-900/30 border border-green-700 rounded p-2">
                {log.new_pull_request_title}
              </div>
            </div>
          )}

        {/* コメントの場合の詳細表示 */}
        {log.action === 'commented' && log.comment && (
          <div className="ml-[-0.7rem] mt-2">
            <div className="text-sm text-gray-300 bg-gray-800 border border-gray-600 rounded p-2">
              {log.comment.content}
            </div>
          </div>
        )}

        <div className="text-xs text-gray-400 mt-2 ml-[-0.7rem]">
          {formatDistanceToNow(new Date(log.created_at), { addSuffix: true, locale: ja })}
        </div>
      </div>
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
  const [isMerging, setIsMerging] = useState(false);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);
  const [conflictStatus, setConflictStatus] = useState<{
    mergeable: boolean | null;
    mergeable_state: string | null;
  }>({ mergeable: null, mergeable_state: null });
  const [isCheckingConflict, setIsCheckingConflict] = useState(false);
  const mergeButtonRef = useRef<HTMLButtonElement | null>(null);
  const [comment, setComment] = useState('');
  const [activeTab, setActiveTab] = useState<TabType>('activity');
  const [comments, setComments] = useState<Comment[]>([]);
  const [loadingComments, setLoadingComments] = useState(false);
  const [activityLogs, setActivityLogs] = useState<ActivityLog[]>([]);
  const [loadingActivityLogs, setLoadingActivityLogs] = useState(false);

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

  // コメント取得API呼び出し関数
  const fetchComments = useCallback(async () => {
    if (!id) return;

    setLoadingComments(true);
    try {
      const response = await apiClient.get(
        `${API_CONFIG.ENDPOINTS.PULL_REQUESTS.GET_DETAIL}/${id}/comments`
      );
      setComments(response || []);
    } catch (error) {
      console.error('コメント取得エラー:', error);
      setToast({
        message: 'コメントの取得に失敗しました',
        type: 'error',
      });
    } finally {
      setLoadingComments(false);
    }
  }, [id]);

  // ActivityLog取得API呼び出し関数
  const fetchActivityLogs = useCallback(async () => {
    if (!id) return;

    setLoadingActivityLogs(true);
    try {
      const logs = await fetchActivityLog(id);
      setActivityLogs(logs);
    } catch (error) {
      console.error('アクティビティログ取得エラー:', error);
      setToast({
        message: 'アクティビティログの取得に失敗しました',
        type: 'error',
      });
    } finally {
      setLoadingActivityLogs(false);
    }
  }, [id]);

  // コンフリクト検知API呼び出し関数
  const checkConflictStatus = useCallback(async () => {
    if (!id || isCheckingConflict || conflictStatus.mergeable !== null) return;

    setIsCheckingConflict(true);
    try {
      const response = await apiClient.get(
        `${API_CONFIG.ENDPOINTS.PULL_REQUESTS.CONFLICT}/${id}/conflict`
      );
      setConflictStatus({
        mergeable: response.mergeable,
        mergeable_state: response.mergeable_state,
      });

      // コンフリクトが検出された場合はトーストで通知
      if (response.mergeable === false) {
        setToast({
          message: 'コンフリクトが検出されました。マージできません。',
          type: 'error',
        });
      }
    } catch (error) {
      console.error('コンフリクト検知エラー:', error);
    } finally {
      setIsCheckingConflict(false);
    }
  }, [id, isCheckingConflict, conflictStatus.mergeable]);

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
    if (showReviewerModal && !reviewersInitialized) {
      handleFetchUser();
    }

    // モーダルを開いた時の初期状態を保存
    if (showReviewerModal) {
      setInitialReviewers([...selectedReviewers]);
    }
  }, [showReviewerModal, reviewersInitialized]);

  // レビュアー検索時の処理
  useEffect(() => {
    if (showReviewerModal && reviewerSearch) {
      const timeoutId = setTimeout(() => {
        handleFetchUser(reviewerSearch);
      }, 300);

      return () => clearTimeout(timeoutId);
    }
  }, [reviewerSearch, showReviewerModal]);

  // 既存のレビュアーをselectedReviewersに設定する処理を削除
  // （上記のfetchData内で処理するため不要）

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

        // プルリクエストデータが取得できた場合、レビュアー設定のためにユーザー一覧を取得
        if (data.reviewers && data.reviewers.length > 0) {
          try {
            const endpoint = API_CONFIG.ENDPOINTS.PULL_REQUEST_REVIEWERS.GET;
            const response = await apiClient.get(endpoint);
            const allUsers = response.users || [];
            setUsers(allUsers);

            // 既存のレビュアーをselectedReviewersに設定
            const reviewerIds = allUsers
              .filter((user: User) =>
                data.reviewers.some((reviewer: Reviewer) => reviewer.email === user.email)
              )
              .map((user: User) => user.id);
            setSelectedReviewers(reviewerIds);
            setInitialReviewers(reviewerIds);
            setReviewersInitialized(true);
          } catch (userError) {
            console.error('初期ユーザー取得エラー:', userError);
          }
        }
      } catch (err) {
        console.error('プルリクエスト詳細取得エラー:', err);
        setError('プルリクエスト詳細の取得に失敗しました');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [id]);

  // アクティビティタブの時にコメントとActivityLogを取得
  useEffect(() => {
    if (activeTab === 'activity' && id) {
      fetchComments();
      fetchActivityLogs();
    }
  }, [activeTab, id, fetchComments, fetchActivityLogs]);

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

  // ボタンの表示を監視してコンフリクトチェックを実行
  useEffect(() => {
    if (
      !mergeButtonRef.current ||
      !pullRequestData ||
      ['merged', 'closed'].includes(pullRequestData.status)
    )
      return;

    const observer = new IntersectionObserver(
      entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            // ボタンが画面に表示されたらコンフリクト検知APIを呼び出し
            checkConflictStatus();
          }
        });
      },
      {
        root: null,
        rootMargin: '0px',
        threshold: 0.1, // ボタンの10%が表示されたら発火
      }
    );

    observer.observe(mergeButtonRef.current);

    return () => {
      observer.disconnect();
    };
  }, [pullRequestData, checkConflictStatus]);

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

  // 変更内容詳細を開く
  const handleViewChanges = () => {
    window.open(`/admin/change-suggestions/${id}/diff`, '_blank');
  };

  // コメント投稿のハンドラー
  const handleComment = async () => {
    if (!comment.trim() || !id) return;

    try {
      await apiClient.post('/api/comments', {
        pull_request_id: parseInt(id),
        content: comment.trim(),
      });

      setToast({ message: 'コメントを投稿しました', type: 'success' });
      setComment('');
      // コメント投稿後にコメントリストを再取得
      fetchComments();
    } catch (error) {
      console.error('コメント投稿エラー:', error);
      setToast({
        message: 'コメント投稿に失敗しました',
        type: 'error',
      });
    }
  };

  // マージボタンのハンドラー
  const handleMerge = async () => {
    if (!id || isMerging) return;

    setIsMerging(true);
    try {
      await apiClient.put(`${API_CONFIG.ENDPOINTS.PULL_REQUESTS.MERGE}/${id}`, {
        pull_request_id: id,
      });

      setToast({ message: 'プルリクエストをマージしました', type: 'success' });
      setTimeout(() => {
        window.location.href = '/admin/change-suggestions';
      }, 1500);
    } catch (error) {
      console.error('マージエラー:', error);
      setToast({
        message:
          'マージに失敗しました: ' + (error instanceof Error ? error.message : '不明なエラー'),
        type: 'error',
      });
    } finally {
      setIsMerging(false);
    }
  };

  // クローズボタンのハンドラー
  const handleClose = async () => {
    if (!id || isMerging) return;

    setIsMerging(true);
    try {
      await apiClient.patch(`${API_CONFIG.ENDPOINTS.PULL_REQUESTS.CLOSE}/${id}/close`);

      setToast({ message: 'プルリクエストを取り下げました', type: 'success' });
      setTimeout(() => {
        window.location.href = '/admin/change-suggestions';
      }, 1500);
    } catch (error) {
      console.error('クローズエラー:', error);
      setToast({
        message:
          '取り下げに失敗しました: ' + (error instanceof Error ? error.message : '不明なエラー'),
        type: 'error',
      });
    } finally {
      setIsMerging(false);
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

  return (
    <AdminLayout title={pullRequestData.title}>
      <style>{markdownStyles}</style>
      <style>{`
        .timeline-container {
          position: relative;
          padding-left: 52px;
        }
        
        .timeline-item {
          position: relative;
          display: flex;
          margin-bottom: 24px;
        }
        
        .timeline-avatar {
          position: absolute;
          left: -52px;
          top: 0;
          z-index: 3;
          background-color: #374151;
          border: 2px solid #4B5563;
          border-radius: 50%;
          width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        
        .timeline-content {
          position: relative;
          z-index: 2;
          flex: 1;
        }
        
        .timeline-content-with-line::after {
          content: "";
          position: absolute;
          left: 15px;
          bottom: -24px;
          width: 2px;
          height: 24px;
          background-color: #4B5563;
          z-index: 1;
        }
      `}</style>
      {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}
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

        {/* タブナビゲーション */}
        <div className="mb-8">
          <nav className="flex">
            {TABS.map(tab => (
              <button
                key={tab.id}
                onClick={() => {
                  if (tab.id === 'changes') {
                    window.location.href = `/admin/change-suggestions/${id}/diff`;
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

        {/* メインコンテンツ */}
        <div className="flex gap-8">
          {/* 左側: 変更概要 */}
          <div className="flex-1">
            <div className="timeline-container">
              {/* プロフィール画像と吹き出し（description） */}
              <div className="timeline-item">
                <div className="timeline-avatar">
                  <span className="text-white text-sm">👤</span>
                </div>
                <div className="timeline-content timeline-content-with-line">
                  <div className="relative border border-gray-600 rounded-lg p-7 w-full max-w-none pt-1">
                    {/* 吹き出しの三角形 */}
                    <div className="absolute left-0 top-4 w-0 h-0 border-t-[8px] border-t-transparent border-r-[12px] border-r-gray-800 border-b-[8px] border-b-transparent transform -translate-x-3"></div>
                    <div className="absolute left-0 top-4 w-0 h-0 border-t-[8px] border-t-transparent border-r-[12px] border-r-gray-600 border-b-[8px] border-b-transparent transform -translate-x-[13px]"></div>

                    {/* ユーザー情報ヘッダー */}
                    <div className="flex items-center justify-between mb-2 ml-[-1rem]">
                      <div className="flex items-center gap-3">
                        <span className="text-white font-semibold text-lg">
                          {pullRequestData?.author_email}
                        </span>
                      </div>
                    </div>

                    <div className="text-white text-base leading-relaxed ml-[-1rem]">
                      {pullRequestData?.description || 'この変更提案には説明がありません。'}
                    </div>
                  </div>
                </div>
              </div>
              {/* アクティビティログリスト（コメント以外） */}
              {loadingActivityLogs ? (
                <div className="timeline-item">
                  <div className="timeline-avatar">
                    <div className="w-5 h-5 animate-spin rounded-full border-t-2 border-b-2 border-white"></div>
                  </div>
                  <div className="timeline-content timeline-content-with-line">
                    <div className="border border-gray-600 rounded-lg p-6 flex-1">
                      <p className="text-gray-400">アクティビティログを読み込み中...</p>
                    </div>
                  </div>
                </div>
              ) : (
                activityLogs
                  .filter(log => log.action !== 'commented') // コメント以外のActivityLogのみ表示
                  .map((log, index) => <ActivityLogItem key={log.id} log={log} />)
              )}

              {/* コメントリスト */}
              {loadingComments ? (
                <div className="timeline-item">
                  <div className="timeline-avatar">
                    <div className="w-5 h-5 animate-spin rounded-full border-t-2 border-b-2 border-white"></div>
                  </div>
                  <div className="timeline-content timeline-content-with-line">
                    <div className="border border-gray-600 rounded-lg p-6 flex-1">
                      <p className="text-gray-400">コメントを読み込み中...</p>
                    </div>
                  </div>
                </div>
              ) : (
                comments.map((commentItem, index) => (
                  <div key={commentItem.id} className="timeline-item">
                    <div className="timeline-avatar">
                      <span className="text-white text-sm">👤</span>
                    </div>
                    <div
                      className={`timeline-content ${index < comments.length - 1 ? 'timeline-content-with-line' : ''}`}
                    >
                      <div className="relative border border-gray-600 rounded-lg p-6 w-full max-w-none pt-1">
                        {/* 吹き出しの三角形 */}
                        <div className="absolute left-0 top-4 w-0 h-0 border-t-[8px] border-t-transparent border-r-[12px] border-r-gray-800 border-b-[8px] border-b-transparent transform -translate-x-3"></div>
                        <div className="absolute left-0 top-4 w-0 h-0 border-t-[8px] border-t-transparent border-r-[12px] border-r-gray-600 border-b-[8px] border-b-transparent transform -translate-x-[13px]"></div>

                        {/* コメントヘッダー */}
                        <div className="flex items-center justify-between mb-2 ml-[-0.7rem]">
                          <div className="flex items-center gap-3">
                            <span className="text-white font-semibold">
                              {commentItem.author || '不明なユーザー'}
                            </span>
                            <span className="text-gray-400 text-sm">
                              {formatDistanceToNow(new Date(commentItem.created_at), {
                                addSuffix: true,
                                locale: ja,
                              })}
                            </span>
                          </div>
                          {commentItem.is_resolved && (
                            <span className="text-green-400 text-sm px-2 py-1 bg-green-900/30 border border-green-700 rounded">
                              解決済み
                            </span>
                          )}
                        </div>

                        {/* 白い区切り線 */}
                        <div className="w-full h-px bg-white mb-3 mx-[-24px]"></div>
                        {/* コメント内容 */}
                        <div className="text-white text-base leading-relaxed whitespace-pre-wrap ml-[-0.7rem]">
                          {commentItem.content}
                        </div>
                      </div>
                    </div>
                  </div>
                ))
              )}
            </div>

            {/* コメントセクション */}
            <div className="flex items-start gap-4 mb-6 relative">
              <div className="w-10 h-10 bg-gray-600 rounded-full flex items-center justify-center flex-shrink-0 relative z-10">
                <span className="text-white text-sm">👤</span>
              </div>
              <div className="border border-gray-600 rounded-lg p-6 flex-1">
                <div className="flex items-center gap-2 mb-2">
                  <span className="text-white font-medium">コメントを追加</span>
                </div>

                <textarea
                  className="w-full border border-gray-600 rounded-md p-3 text-white placeholder-gray-400 resize-none"
                  rows={4}
                  placeholder="コメントを追加してください..."
                  value={comment}
                  onChange={e => setComment(e.target.value)}
                />

                <div className="flex justify-end gap-4 mt-4">
                  <button
                    onClick={handleClose}
                    disabled={isMerging}
                    className="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors disabled:bg-gray-500 disabled:cursor-not-allowed"
                  >
                    提案を取り下げる
                  </button>
                  <button
                    onClick={handleComment}
                    disabled={!comment.trim()}
                    className="px-6 py-2 bg-[#1B6E2A] hover:bg-gray-700 text-white rounded-md transition-colors"
                  >
                    コメントする
                  </button>
                </div>
              </div>
            </div>

            {/* 変更概要ボックス */}
            <div className="flex items-start gap-4 mb-6">
              <div className="w-10 h-10 bg-white rounded-md flex items-center justify-center text-black font-bold text-sm">
                <Merged className="w-5 h-5" />
              </div>
              <div className="border border-gray-600 rounded-lg p-2 flex-1">
                {/* 変更内容ヘッダー */}
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center gap-2">
                    <span className="text-white font-medium">変更概要</span>
                  </div>
                  <Settings
                    className="w-5 h-5 text-gray-400 cursor-pointer hover:text-white"
                    onClick={handleViewChanges}
                  />
                </div>

                {/* 変更統計 */}
                <div className="text-white text-sm mb-4">
                  {pullRequestData && (
                    <>
                      {pullRequestData.document_versions.length > 0 && (
                        <div className="mb-2">
                          📝 ドキュメント: {pullRequestData.document_versions.length}件の変更
                        </div>
                      )}
                      {pullRequestData.document_categories.length > 0 && (
                        <div className="mb-2">
                          📁 カテゴリ: {pullRequestData.document_categories.length}件の変更
                        </div>
                      )}
                    </>
                  )}
                </div>

                {/* コンフリクト状態に応じたメッセージ */}
                {isCheckingConflict ? (
                  <div className="flex items-center gap-2 mb-4 text-blue-400">
                    <svg className="w-5 h-5 animate-spin" fill="currentColor" viewBox="0 0 20 20">
                      <path
                        fillRule="evenodd"
                        d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"
                        clipRule="evenodd"
                      />
                    </svg>
                    <span className="text-sm">コンフリクトをチェック中...</span>
                  </div>
                ) : conflictStatus.mergeable === false ? (
                  <div className="flex items-center gap-2 mb-4 text-red-400">
                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path
                        fillRule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                        clipRule="evenodd"
                      />
                    </svg>
                    <span className="text-sm">
                      コンフリクトが検出されました。マージできません。
                    </span>
                  </div>
                ) : conflictStatus.mergeable === true ? (
                  <div className="flex items-center gap-2 mb-4 text-green-400">
                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path
                        fillRule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clipRule="evenodd"
                      />
                    </svg>
                    <span className="text-sm">他の変更との競合はありません</span>
                  </div>
                ) : (
                  <div className="flex items-center gap-2 mb-4 text-orange-400">
                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                      <path
                        fillRule="evenodd"
                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                        clipRule="evenodd"
                      />
                    </svg>
                    <span className="text-sm">他の変更との競合がないか確認してください</span>
                  </div>
                )}

                {/* 変更を反映するボタン */}
                {pullRequestData &&
                  ![PULL_REQUEST_STATUS.MERGED, PULL_REQUEST_STATUS.CLOSED].includes(
                    pullRequestData.status as any
                  ) && (
                    <div className="flex justify-end mb-4">
                      <button
                        ref={mergeButtonRef}
                        onClick={handleMerge}
                        disabled={isMerging || conflictStatus.mergeable === false}
                        className={`px-6 py-2 font-bold rounded-md transition-colors ${
                          conflictStatus.mergeable === false
                            ? 'bg-red-600 hover:bg-red-700 cursor-not-allowed'
                            : 'bg-blue-600 hover:bg-blue-700'
                        } text-white disabled:bg-gray-500 disabled:cursor-not-allowed`}
                      >
                        {isMerging
                          ? 'マージ中...'
                          : isCheckingConflict
                            ? 'コンフリクトチェック中...'
                            : conflictStatus.mergeable === false
                              ? 'コンフリクトのため反映できません'
                              : '変更を反映する'}
                      </button>
                    </div>
                  )}
              </div>
            </div>
          </div>

          {/* 右側: レビュアー */}
          <div className="w-80">
            <div className="flex items-center gap-2 mb-4 relative" ref={reviewerModalRef}>
              <span className="text-white text-base font-bold">レビュアー</span>
              <Settings
                className="w-5 h-5 text-gray-300 cursor-pointer hover:text-white"
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
            {pullRequestData?.reviewers && pullRequestData.reviewers.length > 0 ? (
              <div className="space-y-2">
                {pullRequestData.reviewers.map((reviewer, index) => (
                  <div key={index} className="flex items-center gap-2 text-sm">
                    <span className="text-xl">👤</span>
                    <span className="text-gray-300">{reviewer.email}</span>
                    {reviewer.action_status === 'approved' && (
                      <span className="text-green-400">✅</span>
                    )}
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-gray-400 text-sm">レビュアーなし</p>
            )}
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
