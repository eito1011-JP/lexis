import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { Home } from '@/components/icon/common/Home';
import { ThreeDots } from '@/components/icon/common/ThreeDots';
import { Toast } from '@/components/admin/Toast';

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
  created_at: string;
};

/**
 * 変更提案一覧ページコンポーネント
 */
export default function ChangeSuggestionsPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/login', false);
  const [categories, setCategories] = useState<Category[]>([]);
  const [documents, setDocuments] = useState<DocumentItem[]>([]);
  const [changeProposals, setChangeProposals] = useState<ChangeProposal[]>([]);
  const [categoriesLoading, setCategoriesLoading] = useState(true);
  const [documentsLoading, setDocumentsLoading] = useState(true);
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

  useEffect(() => {
    const getChangeSuggestions = async () => {
      try {
        // 変更提案一覧を取得
        const changeProposalsResponse = await apiClient.get(`${API_CONFIG.ENDPOINTS.PULL_REQUESTS.GET}`);

        if (changeProposalsResponse) {
          setChangeProposals(changeProposalsResponse.pull_requests || []);
        }
      } catch (err) {
        console.error('変更提案取得エラー:', err);
        setApiError('変更提案の取得に失敗しました');
      } finally {
        setCategoriesLoading(false);
        setDocumentsLoading(false);
        setChangeProposalsLoading(false);
      }
    };

    getChangeSuggestions();
  }, []);

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

  // 変更提案一覧テーブル
  const renderChangeProposalsTable = () => {
    if (changeProposalsLoading) {
      return (
        <div className="flex justify-center py-6">
          <div className="animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-white"></div>
        </div>
      );
    }

    return (
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y">
          <thead className="">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                タイトル
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                提案者
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                作成日時
              </th>
              <th className="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">
                アクション
              </th>
            </tr>
          </thead>
          {changeProposals.length > 0 ? (
            <tbody className="divide-y">
              {changeProposals.map((proposal, index) => (
                <tr key={index} className="hover:bg-gray-800 border-t border-b border-gray-700">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center">
                      <div className="ml-4">
                        <div className="text-sm font-medium text-white">
                          {proposal.title}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                    {proposal.email || '不明'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                    {(() => {
                      const d = new Date(proposal.created_at);
                      const yyyy = d.getFullYear();
                      const m = d.getMonth() + 1;
                      const dd = d.getDate();
                      const hh = d.getHours();
                      const mm = d.getMinutes().toString().padStart(2, '0');
                      return `${yyyy}/${m}/${dd} ${hh}:${mm}`;
                    })()}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div className="flex items-center justify-end relative">
                      <button
                        className="focus:outline-none"
                        onClick={() => setOpenMenuIndex(openMenuIndex === index ? null : index)}
                      >
                        <ThreeDots className="w-4 h-4" />
                      </button>
                      {openMenuIndex === index && (
                        <>
                          {/* 背景クリックで閉じる */}
                          <div className="fixed inset-0 z-40" onClick={handleCloseMenu} />
                          {/* ThreeDotsのすぐ下にabsolute配置 */}
                          <div
                            className="fixed w-30 mr-6 bg-gray-900 border border-gray-700 rounded-md shadow-lg z-50"
                            style={{ zIndex: 100 }}
                            onClick={e => e.stopPropagation()}
                          >
                            <ul className="py-1">
                              <li>
                                <a
                                  href={`/admin/change-proposals/${proposal.id}`}
                                  className="block px-4 py-2 text-sm text-white hover:bg-gray-800 cursor-pointer text-left"
                                  style={{ textAlign: 'left' }}
                                >
                                  詳細を見る
                                </a>
                              </li>
                              <li>
                                <button
                                  className="block w-full text-left px-4 py-2 text-sm text-blue-400 hover:bg-gray-800 cursor-pointer"
                                  style={{ textAlign: 'left' }}
                                  onClick={() => {
                                    // GitHubのプルリクエストページを開く
                                    window.open(`https://github.com/your-repo/pull/${proposal.id}`, '_blank');
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
                  </td>
                </tr>
              ))}
            </tbody>
          ) : (
            <tbody className="divide-y">
              <tr>
                <td colSpan={5} className="text-gray-400 py-4">
                  変更提案がありません
                </td>
              </tr>
            </tbody>
          )}
        </table>
      </div>
    );
  };

  return (
    <AdminLayout title="変更提案一覧">
      <div className="flex flex-col h-full">
        <div className="mb-6">
          {/* パンくずリスト */}
          <div className="flex items-center text-sm text-gray-400 mb-4">
            <a href="/change-suggestions" className="hover:text-white">
              <Home className="w-4 h-4 mx-2" />
            </a>
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
              <div className="mt-2 text-sm">
                <p>APIサーバーとの通信に問題があります。開発モードではダミーデータを使用します。</p>
              </div>
            </div>
          )}

          {/* 変更提案一覧テーブル */}
          <div className="mb-8">
            {renderChangeProposalsTable()}
          </div>
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
