import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { ThreeDots } from '@/components/icon/common/ThreeDots';
import { Toast } from '@/components/admin/Toast';
import { formatDateTime } from '@/utils/date';
import { Comment } from '@/components/icon/common/Comment';
import { ChevronDown } from '@/components/icon/common/ChevronDown';
import { Merged } from '@/components/icon/common/Merged';
import { Merge } from '@/components/icon/common/Merge';

// カテゴリの型定義
type Category = {
  id: number;
  slug: string;
  sidebar_label: string;
};

// ドキュメントアイテムの型定義
type DocumentItem = {
  sidebar_label: string | null;
  slug: string | null;
  is_public: boolean;
  status: string;
  last_edited_by: string | null;
  position?: number;
  file_order?: number;
  category_path?: string;
};

// 変更提案の型定義
type ChangeProposal = {
  id: number;
  title: string;
  status: string;
  email: string | null;
  github_url?: string;
  created_at: string;
  comments_count?: number;
};

/**
 * 変更提案一覧ページコンポーネント
 */
export default function ChangeSuggestionsPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/login', false);
  const [changeProposals, setChangeProposals] = useState<ChangeProposal[]>([]);
  const [changeProposalsLoading, setChangeProposalsLoading] = useState(true);
  const [apiError, setApiError] = useState<string | null>(null);
  const [openMenuIndex, setOpenMenuIndex] = useState<number | null>(null);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [documentToDelete, setDocumentToDelete] = useState<DocumentItem | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType] = useState<'success' | 'error'>('success');
  const [filterStatus, setFilterStatus] = useState<'all' | 'pending' | 'completed'>('all');
  const [pendingCount, setPendingCount] = useState(0);
  const [completedCount, setCompletedCount] = useState(0);

  // URLパラメータから完了済み表示フラグを取得
  const isCompletedView =
    new URLSearchParams(window.location.search).getAll('status').includes('merged') &&
    new URLSearchParams(window.location.search).getAll('status').includes('closed');

  // APIレスポンスをフロントエンドの型に変換する関数
  const transformApiResponse = (proposals: any[]): ChangeProposal[] => {
    return proposals.map(proposal => ({
      id: proposal.id,
      title: proposal.title,
      status: proposal.status,
      email: proposal.email,
      github_url: proposal.github_url,
      created_at: proposal.created_at,
      comments_count: 5, // 仮の値として5を設定
    }));
  };

  // 集計データを設定する関数
  const setCounts = (response: any, isCompleted: boolean) => {
    if (isCompleted) {
      setPendingCount(response.total_opened_count || 0);
      setCompletedCount(response.total_closed_count || 0);
    } else {
      setPendingCount(response.total_opened_count || 0);
      setCompletedCount(response.total_closed_count || 0);
    }
  };

  useEffect(() => {
    const getChangeSuggestions = async () => {
      try {
        setChangeProposalsLoading(true);

        const params: Record<string, string> = isCompletedView ? { status: 'merged,closed' } : {};
        const queryString = new URLSearchParams(params).toString();
        const apiUrl = `${API_CONFIG.ENDPOINTS.PULL_REQUESTS.GET}${queryString ? '?' + queryString : ''}`;

        const response = await apiClient.get(apiUrl);

        if (!response?.pull_requests) {
          console.error('API response does not contain pull_requests:', response);
          setApiError('APIレスポンスの形式が正しくありません');
          return;
        }

        const mappedProposals = transformApiResponse(response.pull_requests);
        setChangeProposals(mappedProposals);
        setCounts(response, isCompletedView);
      } catch (err) {
        console.error('変更提案取得エラー:', err);
        setApiError('変更提案の取得に失敗しました');
      } finally {
        setChangeProposalsLoading(false);
      }
    };

    getChangeSuggestions();
  }, [isCompletedView]);

  const handleCloseMenu = () => {
    setOpenMenuIndex(null);
  };

  // ドキュメント削除のハンドラー
  const handleDeleteDocument = async () => {
    if (!documentToDelete || !documentToDelete.slug) return;

    setIsDeleting(true);
    setDeleteError(null);

    try {
      await apiClient.delete(
        `${API_CONFIG.ENDPOINTS.DOCUMENTS.DELETE}?category_path_with_slug=${documentToDelete.slug}`
      );

      // 即座にページをリロード
      window.location.reload();

      // トーストメッセージを表示
      setToastMessage('ドキュメントが削除されました');
      setToastType('success');
      setShowToast(true);
    } catch (err) {
      console.error('ドキュメント削除エラー:', err);
      setDeleteError('ドキュメントの削除中にエラーが発生しました');
    } finally {
      setIsDeleting(false);
    }
  };

  // 削除確認モーダルを閉じる
  const closeDeleteModal = () => {
    setShowDeleteModal(false);
    setDocumentToDelete(null);
    setDeleteError(null);
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

  // フィルタリング機能
  const filteredProposals = changeProposals.filter(proposal => {
    // 完了済み表示の場合は、既にAPIでフィルタリングされているためそのまま表示
    if (isCompletedView) return true;

    if (filterStatus === 'all') return true;
    return proposal.status === filterStatus;
  });

  // 変更提案一覧カード表示
  const renderChangeProposalsCards = () => {
    if (changeProposalsLoading) {
      return (
        <div className="flex justify-center py-6">
          <div className="animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-white"></div>
        </div>
      );
    }

    if (filteredProposals.length === 0) {
      return (
        <div className="text-center py-12 text-gray-400">
          {isCompletedView
            ? '完了済みの変更提案がありません'
            : filterStatus === 'all'
              ? '変更提案がありません'
              : `${filterStatus === 'pending' ? '未対応' : '完了済み'}の変更提案がありません`}
        </div>
      );
    }

    return (
      <div className="space-y-1">
        {filteredProposals.map((proposal, index) => (
          <div
            key={proposal.id}
            className="bg-gray-800 border border-gray-700 rounded-lg p-4 hover:bg-gray-700 transition-colors cursor-pointer"
            onClick={e => {
              // メニューボタンがクリックされた場合は詳細ページに遷移しない
              if (!(e.target as Element).closest('.menu-button')) {
                window.location.href = `/admin/change-suggestions/${proposal.id}`;
              }
            }}
          >
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-3">
                {/* ステータスインジケーター */}
                <div className="flex items-center">
                  <input
                    type="checkbox"
                    className="w-4 h-4 accent-green-500 border-2 border-gray-400 rounded focus:ring-2 focus:ring-green-500"
                    checked={isCompletedView || proposal.status === 'completed'}
                    onChange={() => {
                      // 完了済み表示の場合は状態変更を無効にする
                      if (isCompletedView) return;

                      setChangeProposals(prev =>
                        prev.map(p =>
                          p.id === proposal.id
                            ? { ...p, status: p.status === 'completed' ? 'pending' : 'completed' }
                            : p
                        )
                      );
                    }}
                    disabled={isCompletedView}
                  />
                </div>

                {proposal.status === 'merged' && <Merged className="w-4 h-4 text-[#3832A5]" />}
                {proposal.status === 'opened' && <Merge className="w-4 h-4 text-[#00D85B]" />}
                {proposal.status === 'closed' && <Merge className="w-4 h-4 text-[#DA3633]" />}

                {/* タイトル */}
                <div className="flex-1">
                  <h3
                    className="text-white font-medium text-sm hover:underline hover:text-blue-400 cursor-pointer transition-colors"
                    onClick={e => {
                      e.stopPropagation();
                      window.location.href = `/admin/change-suggestions/${proposal.id}`;
                    }}
                  >
                    {proposal.title}
                  </h3>
                  <div className="flex items-center space-x-4 text-xs text-gray-400 mt-1">
                    <span>
                      {formatDateTime(proposal.created_at)} に {proposal.email} から提案されました
                    </span>
                  </div>
                </div>
              </div>

              {/* 右側のコメント数とメニュー */}
              <div className="flex items-center space-x-6">
                {/* コメント数 */}
                <div className="flex items-center text-gray-400 gap-1">
                  <Comment className="w-7 h-7 text-gray-400 mt-2" />
                  <span className="text-sm w-4 text-left">{proposal.comments_count || 0}</span>
                </div>

                {/* メニューボタン */}
                <div className="relative menu-button">
                  <button
                    className="focus:outline-none p-1 rounded hover:bg-gray-700 transition-colors"
                    onClick={e => {
                      e.stopPropagation();
                      setOpenMenuIndex(openMenuIndex === index ? null : index);
                    }}
                  >
                    <ThreeDots className="w-4 h-4 text-gray-400" />
                  </button>

                  {openMenuIndex === index && (
                    <>
                      <div className="fixed inset-0 z-40" onClick={handleCloseMenu} />
                      <div className="absolute right-0 mt-2 w-48 bg-gray-900 border border-gray-700 rounded-md shadow-lg z-50">
                        <ul className="py-1">
                          <li>
                            <button
                              className="block w-full text-left px-4 py-2 text-sm text-blue-400 hover:bg-gray-800 cursor-pointer"
                              onClick={() => {
                                window.open(proposal.github_url, '_blank');
                              }}
                            >
                              GitHubで開く
                            </button>
                          </li>
                        </ul>
                      </div>
                    </>
                  )}
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  };

  return (
    <AdminLayout title={isCompletedView ? '変更提案 - 完了済み' : '変更提案'}>
      <div className="flex flex-col h-full">
        {/* ヘッダー部分 */}
        <div className="mb-6">
          <div className="flex items-center justify-between mb-6">
            {/* ステータス表示 */}
            <div className="flex items-center space-x-6">
              <div className="flex items-center space-x-2 gap-2">
                <div className="flex items-center space-x-2">
                  <Merge className="w-4 h-4 text-[#1FF258]" />
                  <a
                    href="/admin/change-suggestions"
                    className={`text-bold hover:underline ${!isCompletedView ? 'text-white' : 'text-gray-400'}`}
                  >
                    {pendingCount} 未対応
                  </a>
                </div>
                <div className="flex items-center space-x-2">
                  <Merged className="w-4 h-4 text-[#3832A5]" />
                  <a
                    href="/admin/change-suggestions?status=merged&status=closed"
                    className={`text-bold hover:underline ${isCompletedView ? 'text-white' : 'text-gray-400'}`}
                  >
                    {completedCount} 完了済み
                  </a>
                </div>
              </div>
            </div>

            {/* フィルターとソート */}
            <div className="flex items-center space-x-4 mr-25">
              <button className="text-sm text-gray-400 hover:text-white flex items-center space-x-1">
                <span>提案者</span>
                <ChevronDown className="w-2 h-2" />
              </button>

              <button className="text-sm text-gray-400 hover:text-white flex items-center space-x-1">
                <span>確認対応者</span>
                <ChevronDown className="w-2 h-2" />
              </button>
            </div>
          </div>

          {/* APIエラー表示 */}
          {apiError && (
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
                <span>{apiError}</span>
              </div>
            </div>
          )}

          {/* 変更提案一覧 */}
          <div className="mb-8">{renderChangeProposalsCards()}</div>
        </div>
      </div>

      {/* 削除確認モーダル */}
      {showDeleteModal && documentToDelete && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-gray-900 p-6 rounded-lg w-full max-w-md">
            <h2 className="text-xl font-bold mb-4">ドキュメントを削除</h2>

            {deleteError && (
              <div className="mb-4 p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
                {deleteError}
              </div>
            )}

            <p className="mb-4 text-gray-300">
              「{documentToDelete.sidebar_label || documentToDelete.slug}」を削除しますか？
            </p>

            <div className="flex justify-end gap-2">
              <button
                className="px-4 py-2 bg-gray-800 rounded-md hover:bg-gray-700 focus:outline-none"
                onClick={closeDeleteModal}
                disabled={isDeleting}
              >
                キャンセル
              </button>
              <button
                className="px-4 py-2 bg-red-600 rounded-md hover:bg-red-700 focus:outline-none flex items-center"
                onClick={handleDeleteDocument}
                disabled={isDeleting}
              >
                {isDeleting ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mr-2"></div>
                    <span>削除中...</span>
                  </>
                ) : (
                  <span>はい</span>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Toast */}
      {showToast && (
        <Toast message={toastMessage} type={toastType} onClose={() => setShowToast(false)} />
      )}
    </AdminLayout>
  );
}
