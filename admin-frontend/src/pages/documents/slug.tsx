import AdminLayout from '@/components/admin/layout';
import React, { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { apiClient } from '@/components/admin/api/client';
import { MultipleFolder } from '@/components/icon/common/MultipleFolder';
import { Folder } from '@/components/icon/common/Folder';
import { Home } from '@/components/icon/common/Home';
import { API_CONFIG } from '@/components/admin/api/config';
import EditDocumentPage from './[slug]/edit';
import { ThreeDots } from '@/components/icon/common/ThreeDots';

// アイテムの型定義
type Item = {
  type: 'folder' | 'file';
  slug: string;
  label: string;
  sidebarLabel?: string;
  name?: string;
  position?: number;
  description?: string;
  isDraft: boolean;
  lastEditedBy?: string;
  content?: string;
};

type Category = {
  slug: string;
  sidebarLabel: string;
  position?: number;
  description?: string;
  isDraft: boolean;
};

type Document = {
  slug: string;
  sidebarLabel: string;
  isPublic: boolean;
  status: string;
  lastEditedBy: string | null;
  fileOrder: number | null;
};

/**
 * 管理画面のドキュメントカテゴリ詳細ページコンポーネント
 */
export default function DocumentBySlugPage(): JSX.Element {
  const location = useLocation();
  // /editで終わるパスはEditDocumentPageをレンダリング
  if (location.pathname.endsWith('/edit')) {
    return <EditDocumentPage />;
  }
  const slug = location.pathname.replace('/documents/', '');
  const { isLoading } = useSessionCheck('/login', false);
  const [showCategoryModal, setShowCategoryModal] = useState(false);
  const [categorySlug, setCategorySlug] = useState('');
  const [label, setLabel] = useState('');
  const [position, setPosition] = useState('');
  const [description, setDescription] = useState('');
  const [isCreating, setIsCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [items, setItems] = useState<Item[]>([]);
  const [itemsLoading, setItemsLoading] = useState(true);
  const [apiError, setApiError] = useState<string | null>(null);
  const [showPrSubmitButton, setShowPrSubmitButton] = useState(false);
  const [showSubmitModal, setShowSubmitModal] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState<string | null>(null);
  const [currentCategory, setCurrentCategory] = useState<Item | null>(null);
  const [invalidSlug, setInvalidSlug] = useState<string | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [documents, setDocuments] = useState<Document[]>([]);
  const [categoriesLoading, setCategoriesLoading] = useState(true);
  const [documentsLoading, setDocumentsLoading] = useState(true);
  const [openMenuIndex, setOpenMenuIndex] = useState<number | null>(null);
  const handleCloseMenu = () => setOpenMenuIndex(null);

  // 予約語やルーティングで使用される特殊パターン
  const reservedSlugs = ['create', 'edit', 'new', 'delete', 'update'];

  // slugのバリデーション関数
  const validateSlug = (value: string) => {
    // 空の場合はエラーなし（必須チェックは別で行う）
    if (!value.trim()) {
      setInvalidSlug(null);
      return;
    }

    // 予約語チェック
    if (reservedSlugs.includes(value.toLowerCase())) {
      setInvalidSlug(`"${value}" は予約語のため使用できません`);
      return;
    }

    // URLで問題になる文字をチェック
    if (!/^[a-z0-9-]+$/i.test(value)) {
      setInvalidSlug('英数字とハイフン(-)のみ使用できます');
      return;
    }

    setInvalidSlug(null);
  };

  useEffect(() => {
    const getDocuments = async () => {
      try {
        const currentPath = location.pathname;
        const pathAfterDocuments = currentPath.replace('/documents/', '');
        const response = await apiClient.get(
          `${API_CONFIG.ENDPOINTS.DOCUMENTS.GET_DOCUMENT}?slug=${pathAfterDocuments}`
        );

        if (response) {
          setCategories(response.categories);
          setDocuments(response.documents);
        }
        const hasUserDraft = await apiClient.get(API_CONFIG.ENDPOINTS.GIT.CHECK_DIFF);
        if (hasUserDraft && hasUserDraft.exists) {
          setShowPrSubmitButton(true);
        }
      } catch (err) {
        setApiError('ドキュメントの取得に失敗しました');
      } finally {
        setCategoriesLoading(false);
        setDocumentsLoading(false);
      }
    };

    getDocuments();
  }, []);

  const handleCreateCategoryModal = async () => {
    if (!slug) return;

    setShowCategoryModal(true);
  };

  const handleCloseModal = () => {
    setShowCategoryModal(false);
    setCategorySlug('');
    setLabel('');
    setPosition('');
    setDescription('');
    setInvalidSlug(null);
  };

  const handleCreateNewCategory = async () => {
    if (!categorySlug.trim()) return;

    // 表示順のバリデーション：数値以外が入力されていたらエラー
    if (position.trim() !== '' && isNaN(Number(position))) {
      setError('表示順は数値を入力してください');
      return;
    }

    // slugのバリデーション
    if (invalidSlug) {
      setError(invalidSlug);
      return;
    }

    setIsCreating(true);
    setError(null);

    try {
      // positionを数値に変換
      const positionNum = position ? parseInt(position, 10) : undefined;

      const response = await apiClient.post(API_CONFIG.ENDPOINTS.DOCUMENTS.CREATE_FOLDER, {
        slug: categorySlug,
        sidebarLabel: label,
        position: positionNum,
        description,
        categoryPath: slug ? [slug] : [], // 親カテゴリのパスを追加
      });

      // 新しいカテゴリをリストに追加
      if (response.slug) {
        setItems(prev => [
          ...prev,
          {
            type: 'folder',
            slug: response.slug,
            label: response.label,
            position: positionNum,
            description: description,
            isDraft: false,
          },
        ]);
      }
      handleCloseModal();
      window.location.reload();
    } catch (err) {
      console.error('カテゴリ作成エラー:', err);
      setError(err instanceof Error ? err.message : '不明なエラーが発生しました');
    } finally {
      setIsCreating(false);
    }
  };

  // 差分提出のハンドラー
  const handleSubmitDiff = async () => {
    setIsSubmitting(true);
    setSubmitError(null);
    setSubmitSuccess(null);

    try {
      const response = await apiClient.post('/admin/git/create-pr', {
        title: '更新内容の提出',
        description: 'このPRはハンドブックの更新を含みます。',
      });

      if (response.success) {
        setShowSubmitModal(false);
        setShowPrSubmitButton(false);
        setSubmitSuccess('差分の提出が完了しました');
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

  // カテゴリセクション
  const renderContentsSection = () => {
    if (categoriesLoading) {
      return (
        <div className="flex justify-center py-6">
          <div className="animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-white"></div>
        </div>
      );
    }

    if (categories.length === 0) {
      return <p className="text-gray-400 py-4">カテゴリがありません</p>;
    }

    return (
      <div className="grid grid-cols-2 gap-4">
        {categories.map((category, index) => (
          <div
            key={index}
            onClick={() => {
              window.location.href = `/admin/documents/${slug ? `${slug}/` : ''}${category.slug}`;
            }}
            className="flex items-center justify-between p-3 bg-gray-900 rounded-md border border-gray-800 hover:bg-gray-800 cursor-pointer"
          >
            <div className="flex items-center">
              <Folder className="w-5 h-5 mr-2" />
              <span className="text-white hover:underline">{category.sidebarLabel}</span>
            </div>
            <div
              onClick={e => {
                e.stopPropagation();
              }}
            >
              <ThreeDots className="w-4 h-4 text-gray-400 hover:text-white" />
            </div>
          </div>
        ))}
      </div>
    );
  };

  // ドキュメント一覧テーブル
  const renderDocumentTable = () => {
    if (documentsLoading) {
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
                作業ステータス
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                最終編集者
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                公開ステータス
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                表示順序
              </th>
              <th className="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">
                アクション
              </th>
            </tr>
          </thead>
          {documents.length > 0 ? (
            <tbody className="divide-y">
              {documents.map((document, index) => (
                <tr key={index} className="hover:bg-gray-800">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center">
                      <div className="ml-4">
                        <div className="text-sm font-medium text-white">
                          {document.sidebarLabel}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span
                      className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                        document.status === 'draft'
                          ? 'bg-blue-100 text-blue-800'
                          : document.status === 'pushed'
                            ? 'bg-yellow-100 text-yellow-800'
                            : 'bg-green-100 text-green-800'
                      }`}
                    >
                      {document.status === 'draft'
                        ? '編集中'
                        : document.status === 'pushed'
                          ? 'レビュー中'
                          : '完了'}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                    {document.lastEditedBy || '未設定'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span
                      className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                        document.isPublic
                          ? 'bg-green-100 text-green-800'
                          : 'bg-red-100 text-red-800'
                      }`}
                    >
                      {document.isPublic ? '公開' : '非公開'}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                    {document.fileOrder !== null ? document.fileOrder : '-'}
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
                          <div className="fixed inset-0 z-40" onClick={handleCloseMenu} />
                          <div
                            className="absolute right-0 top-full mt-2 w-40 bg-gray-900 border border-gray-700 rounded-md shadow-lg z-50"
                            style={{ zIndex: 100 }}
                            onClick={e => e.stopPropagation()}
                          >
                            <ul className="py-1">
                              <li>
                                <Link
                                  to={`/documents/${slug ? `${slug}/` : ''}${document.slug}/edit`}
                                  className="block px-4 py-2 text-sm text-white hover:bg-gray-800 cursor-pointer text-left"
                                  style={{ textAlign: 'left' }}
                                >
                                  編集する
                                </Link>
                              </li>
                              <li>
                                <button
                                  className="block w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-gray-800 cursor-pointer"
                                  style={{ textAlign: 'left' }}
                                  onClick={() => {
                                    alert('削除機能は未実装です');
                                    handleCloseMenu();
                                  }}
                                >
                                  削除する
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
                <td colSpan={6} className="text-gray-400 py-4">
                  ドキュメントがありません
                </td>
              </tr>
            </tbody>
          )}
        </table>
      </div>
    );
  };

  // パンくずリスト
  const renderBreadcrumbs = () => {
    if (!slug) return null;

    // スラグを/で分割して階層構造を作成
    const slugParts = slug.split('/');
    let currentPath = '';

    return (
      <div className="flex items-center text-sm text-gray-400 mb-4">
        <div
          onClick={() => {
            window.location.href = '/admin/documents';
          }}
          className="hover:text-white"
        >
          <Home className="w-4 h-4 mx-2" />
        </div>
        {/* スラグの各部分に対してパンくずを生成 */}
        {slugParts.map((part, index) => {
          // パスを構築（現在までの部分）
          currentPath += (index === 0 ? '' : '/') + part;
          const path = `/admin/documents/${currentPath}`;

          return (
            <React.Fragment key={index}>
              <span className="mx-2">
                <svg
                  className="w-3 h-3"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M9 5l7 7-7 7"
                  ></path>
                </svg>
              </span>
              {index === slugParts.length - 1 ? (
                <span className="text-white">{part}</span>
              ) : (
                <div
                  onClick={() => {
                    window.location.href = path;
                  }}
                  className="hover:text-white"
                >
                  {part}
                </div>
              )}
            </React.Fragment>
          );
        })}
      </div>
    );
  };

  return (
    <AdminLayout title={`${slug || 'ドキュメント'} - ドキュメント管理`}>
      <div className="flex flex-col h-full">
        <div className="mb-6">
          {renderBreadcrumbs()}

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

          {/* 差分提出の成功メッセージ */}
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
            </div>
          )}

          {/* 差分提出のエラーメッセージ */}
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

          {/* 検索とアクションエリア */}
          <div className="flex items-center justify-between mb-6">
            <div className="flex items-center gap-4 ml-auto">
              <button
                className="flex items-center px-3 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none"
                onClick={() => setShowSubmitModal(true)}
                disabled={!showPrSubmitButton}
              >
                <svg
                  className="w-5 h-5 mr-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"
                  ></path>
                </svg>
                <span>差分提出</span>
              </button>

              <Link
                to={`/documents/create?category=${slug}`}
                className="flex items-center px-3 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none"
              >
                <svg
                  className="w-5 h-5 mr-2"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                  ></path>
                </svg>
                <span>新規ドキュメント</span>
              </Link>

              <button
                className="flex items-center px-3 py-2 bg-gray-700 rounded-md hover:bg-gray-600 focus:outline-none"
                onClick={handleCreateCategoryModal}
              >
                <MultipleFolder className="w-5 h-5 mr-2" />
                <span>新規カテゴリ</span>
              </button>
            </div>
          </div>

          {/* ドキュメント一覧テーブル */}
          <div className="mb-8">
            <h2 className="text-xl font-bold mb-4">ドキュメント</h2>
            {renderDocumentTable()}
          </div>

          {/* カテゴリセクション */}
          <div className="mb-8">
            <h2 className="text-xl font-bold mb-4">カテゴリ</h2>
            {renderContentsSection()}
          </div>
        </div>
      </div>

      {/* カテゴリ作成モーダル */}
      {showCategoryModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-gray-900 p-6 rounded-lg w-full max-w-md">
            <h2 className="text-xl font-bold mb-4">新しいカテゴリを作成</h2>

            {error && (
              <div className="mb-4 p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
                {error}
              </div>
            )}

            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-400">Slug</label>
              <input
                type="text"
                className={`w-full p-2 bg-gray-800 border ${invalidSlug ? 'border-red-500' : 'border-gray-700'} rounded-md focus:outline-none focus:border-[#3832A5] mb-2`}
                placeholder="sample-document"
                value={categorySlug}
                onChange={e => {
                  const value = e.target.value;
                  setCategorySlug(value);
                  validateSlug(value);
                }}
              />
              {invalidSlug && <p className="text-red-500 text-xs mt-1 mb-2">{invalidSlug}</p>}
              <label className="block text-sm font-medium text-gray-400">カテゴリ名</label>
              <input
                type="text"
                className="w-full p-2 bg-gray-800 border border-gray-700 rounded-md focus:outline-none focus:border-[#3832A5] mb-2"
                placeholder="カテゴリ名を入力"
                value={label}
                onChange={e => setLabel(e.target.value)}
              />
              <label className="block text-sm font-medium text-gray-400">表示順</label>
              <input
                type="text"
                className="w-full p-2 bg-gray-800 border border-gray-700 rounded-md focus:outline-none focus:border-[#3832A5] mb-2"
                placeholder="1"
                value={position}
                onChange={e => setPosition(e.target.value)}
              />
              <label className="block text-sm font-medium text-gray-400">説明</label>
              <input
                type="text"
                className="w-full p-2 bg-gray-800 border border-gray-700 rounded-md focus:outline-none focus:border-[#3832A5] mb-2"
                placeholder="カテゴリの説明を入力"
                value={description}
                onChange={e => setDescription(e.target.value)}
              />
            </div>

            <div className="flex justify-end gap-2">
              <button
                className="px-4 py-2 bg-gray-800 rounded-md hover:bg-gray-700 focus:outline-none"
                onClick={handleCloseModal}
              >
                キャンセル
              </button>
              <button
                className="px-4 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none flex items-center"
                onClick={handleCreateNewCategory}
                disabled={!categorySlug.trim() || !label.trim() || isCreating}
              >
                {isCreating ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mr-2"></div>
                    <span>作成中...</span>
                  </>
                ) : (
                  <span>作成</span>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 差分提出確認モーダル */}
      {showSubmitModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-gray-900 p-6 rounded-lg w-full max-w-md">
            <h2 className="text-xl font-bold mb-4">変更内容を提出</h2>

            <p className="mb-4 text-gray-300">
              作成した変更内容をレビュー用に提出します。よろしいですか？
            </p>

            <div className="flex justify-end gap-2">
              <button
                className="px-4 py-2 bg-gray-800 rounded-md hover:bg-gray-700 focus:outline-none"
                onClick={() => setShowSubmitModal(false)}
                disabled={isSubmitting}
              >
                キャンセル
              </button>
              <button
                className="px-4 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none flex items-center"
                onClick={handleSubmitDiff}
                disabled={isSubmitting}
              >
                {isSubmitting ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mr-2"></div>
                    <span>提出中...</span>
                  </>
                ) : (
                  <span>提出する</span>
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </AdminLayout>
  );
}
