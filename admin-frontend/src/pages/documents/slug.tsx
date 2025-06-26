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
import DiffDocumentPage from './diff';
import { Toast } from '@/components/admin/Toast';

// アイテムの型定義
type Item = {
  type: 'folder' | 'file';
  slug: string;
  label: string;
  sidebar_label?: string;
  name?: string;
  position?: number;
  description?: string;
  is_draft: boolean;
  last_edited_by?: string;
  content?: string;
};

type Category = {
  id: number;
  slug: string;
  sidebar_label: string;
  position?: number;
  description?: string;
  is_draft: boolean;
};

type Document = {
  slug: string;
  sidebar_label: string;
  is_public: boolean;
  status: string;
  last_edited_by: string | null;
  file_order: number | null;
};

/**
 * 管理画面のドキュメントカテゴリ詳細ページコンポーネント
 */
export default function DocumentBySlugPage(): JSX.Element {
  const location = useLocation();
  // /editで終わるパスはEditDocumentPageをレンダリング
  if (location.pathname.endsWith('/edit')) {
    return <EditDocumentPage />;
  } else if (location.pathname.endsWith('/diff')) {
    return <DiffDocumentPage />;
  }
  // /admin/documentsより下のパスを取得（可変長に対応）
  const pathAfterDocuments = location.pathname.replace('/documents/', '');
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
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [documentToDelete, setDocumentToDelete] = useState<Document | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType] = useState<'success' | 'error'>('success');
  const [openCategoryMenuIndex, setOpenCategoryMenuIndex] = useState<number | null>(null);
  const [showCategoryDeleteModal, setShowCategoryDeleteModal] = useState(false);
  const [categoryToDelete, setCategoryToDelete] = useState<Category | null>(null);
  const [isCategoryDeleting, setIsCategoryDeleting] = useState(false);
  const [categoryDeleteError, setCategoryDeleteError] = useState<string | null>(null);
  const [showCategoryEditModal, setShowCategoryEditModal] = useState(false);
  const [editingCategory, setEditingCategory] = useState<Category | null>(null);
  const [isEditing, setIsEditing] = useState(false);

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
        const response = await apiClient.get(
          `${API_CONFIG.ENDPOINTS.DOCUMENTS.GET}?category_path=${pathAfterDocuments}`
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
  }, [pathAfterDocuments]);

  const handleCreateCategoryModal = async () => {
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
    // ダブルクリック防止：既に処理中の場合は何もしない
    if (isCreating) {
      return;
    }

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

      const response = await apiClient.post(
        `${API_CONFIG.ENDPOINTS.CATEGORIES.CREATE}`,
        {
          category_path: pathAfterDocuments,
          slug: categorySlug,
          sidebar_label: label,
          position: positionNum,
          description,
        }
      );

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
            is_draft: false,
          },
        ]);
      }
      handleCloseModal();
      setToastMessage('カテゴリが作成されました');
      setToastType('success');
      setShowToast(true);
      window.location.reload();
    } catch (err) {
      console.error('カテゴリ作成エラー:', err);
      setError(err instanceof Error ? err.message : '不明なエラーが発生しました');
      setToastMessage(err instanceof Error ? err.message : '不明なエラーが発生しました');
      setToastType('error');
      setShowToast(true);
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
      const response = await apiClient.get(API_CONFIG.ENDPOINTS.GIT.GET_DIFF, {
        params: {
          title: '更新内容の提出',
          description: 'このPRはハンドブックの更新を含みます。',
        },
      });

      if (response.success) {
        setShowSubmitModal(false);
        setShowPrSubmitButton(false);
        setToastMessage('差分の提出が完了しました');
        setToastType('success');
        setShowToast(true);
      } else {
        setSubmitError(response.message || '差分の提出に失敗しました');
      }
    } catch (err) {
      console.error('差分提出エラー:', err);
      setSubmitError('差分の提出中にエラーが発生しました');
      setToastMessage('差分の提出中にエラーが発生しました');
      setToastType('error');
      setShowToast(true);
    } finally {
      setIsSubmitting(false);
    }
  };

  // ドキュメント削除のハンドラー
  const handleDeleteDocument = async () => {
    if (!documentToDelete) return;

    setIsDeleting(true);
    setDeleteError(null);

    try {
      await apiClient.delete(
        `${API_CONFIG.ENDPOINTS.DOCUMENTS.DELETE}?category_path_with_slug=${pathAfterDocuments}/${documentToDelete.slug}`
      );

      window.location.reload();
        setDocumentToDelete(null);
        setToastMessage('ドキュメントが削除されました');
        setToastType('success');
        setShowToast(true);
    } catch (err) {
      console.error('ドキュメント削除エラー:', err);
      setDeleteError('ドキュメントの削除中にエラーが発生しました');
      setToastMessage('ドキュメントの削除中にエラーが発生しました');
      setToastType('error');
      setShowToast(true);
    } finally {
      setIsDeleting(false);
    }
  };

  // 削除確認モーダルを開く
  const openDeleteModal = (document: Document) => {
    setDocumentToDelete(document);
    setShowDeleteModal(true);
    setOpenMenuIndex(null);
  };

  // 削除確認モーダルを閉じる
  const closeDeleteModal = () => {
    setShowDeleteModal(false);
    setDocumentToDelete(null);
    setDeleteError(null);
  };

  // カテゴリメニューを閉じるためのハンドラ
  const handleCloseCategoryMenu = () => setOpenCategoryMenuIndex(null);

  // カテゴリ編集モーダルを開く
  const handleOpenEditModal = async (category: Category) => {
    console.log('category', category.slug);
    console.log('pathAfterDocuments', pathAfterDocuments);
    const response = await apiClient.get(
      `${API_CONFIG.ENDPOINTS.CATEGORIES.GET}?category_path=${pathAfterDocuments}/${category.slug}`
    );

    console.log('response', response);
    // レスポンスからcategoriesキーでデータを取得
    const categoryData = response.categories;
    if (!categoryData) {
      console.error('カテゴリデータが見つかりません');
      return;
    }

    setEditingCategory(categoryData);
    setCategorySlug(categoryData.slug);
    setLabel(categoryData.sidebar_label);
    setPosition(categoryData.position?.toString() || '');
    setDescription(categoryData.description || '');
    setInvalidSlug(null);
    setShowCategoryEditModal(true);
    setOpenCategoryMenuIndex(null);
  };

  // カテゴリ編集モーダルを閉じる
  const handleCloseEditModal = () => {
    setShowCategoryEditModal(false);
    setEditingCategory(null);
    setCategorySlug('');
    setLabel('');
    setPosition('');
    setDescription('');
    setInvalidSlug(null);
  };

  // カテゴリ編集のハンドラー
  const handleEditCategory = async () => {
    if (!categorySlug.trim() || !editingCategory) return;

    // 表示順のバリデーション：数値以外が入力されていたらエラー
    if (position.trim() !== '' && isNaN(Number(position))) {
      setError('表示順は数値を入力してください');
      return;
    }

    // slugのバリデーション（元のslugと同じ場合はスキップ）
    if (categorySlug !== editingCategory.slug && invalidSlug) {
      setError(invalidSlug);
      return;
    }

    setIsEditing(true);
    setError(null);

    try {
      // positionを数値に変換
      const positionNum = position ? parseInt(position, 10) : undefined;

      await apiClient.put(
        `${API_CONFIG.ENDPOINTS.CATEGORIES.UPDATE}`,
        {
          current_category_id: editingCategory.id,
          category_path: pathAfterDocuments,
          slug: categorySlug,
          sidebar_label: label,
          position: positionNum,
          description,
        }
      );

      setToastMessage('カテゴリが更新されました');
      setToastType('success');
      setShowToast(true);

      window.location.reload();

      handleCloseEditModal();
    } catch (err) {
      console.error('カテゴリ更新エラー:', err);
      setError(err instanceof Error ? err.message : '不明なエラーが発生しました');
    } finally {
      setIsEditing(false);
    }
  };

  // カテゴリ削除のハンドラー
  const handleDeleteCategory = async () => {
    if (!categoryToDelete) return;

    setIsCategoryDeleting(true);
    setCategoryDeleteError(null);

    try {
      const response = await apiClient.delete(
        `${API_CONFIG.ENDPOINTS.CATEGORIES.DELETE}/${categoryToDelete.slug}`
      );

      if (response.success) {
        // カテゴリ一覧から削除されたカテゴリを除去
        setCategories(prev => prev.filter(cat => cat.slug !== categoryToDelete.slug));
        setShowCategoryDeleteModal(false);
        setCategoryToDelete(null);
        
        // トーストメッセージを表示
        setToastMessage('カテゴリが削除されました');
        setToastType('success');
        setShowToast(true);
        
      } else {
        setCategoryDeleteError(response.message || 'カテゴリの削除に失敗しました');
      }
    } catch (err) {
      console.error('カテゴリ削除エラー:', err);
      setCategoryDeleteError('カテゴリの削除中にエラーが発生しました');
    } finally {
      setIsCategoryDeleting(false);
    }
  };

  // カテゴリ削除確認モーダルを開く
  const openCategoryDeleteModal = (category: Category) => {
    setCategoryToDelete(category);
    setShowCategoryDeleteModal(true);
    setOpenCategoryMenuIndex(null);
  };

  // カテゴリ削除確認モーダルを閉じる
  const closeCategoryDeleteModal = () => {
    setShowCategoryDeleteModal(false);
    setCategoryToDelete(null);
    setCategoryDeleteError(null);
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
              window.location.href = `/admin/documents/${pathAfterDocuments}/${category.slug}`;
            }}
            className="flex items-center justify-between p-3 bg-gray-900 rounded-md border border-gray-800 hover:bg-gray-800 cursor-pointer"
          >
            <div className="flex items-center">
              <Folder className="w-5 h-5 mr-2" />
              <span className="text-white hover:underline">{category.sidebar_label}</span>
            </div>
            <div className="relative">
              <div
                onClick={e => {
                  e.stopPropagation();
                  setOpenCategoryMenuIndex(openCategoryMenuIndex === index ? null : index);
                }}
              >
                <ThreeDots className="w-4 h-4 text-gray-400 hover:text-white" />
              </div>
              {openCategoryMenuIndex === index && (
                <>
                  {/* 背景クリックで閉じる */}
                  <div className="fixed inset-0 z-40" onClick={handleCloseCategoryMenu} />
                  <div
                    className="absolute right-0 top-6 w-30 bg-gray-900 border border-gray-700 rounded-md shadow-lg z-50"
                    onClick={e => e.stopPropagation()}
                  >
                    <ul className="py-1">
                      <li>
                        <button
                          className="block w-full text-left px-4 py-2 text-sm text-white hover:bg-gray-800 cursor-pointer"
                          onClick={() => handleOpenEditModal(category)}
                        >
                          編集する
                        </button>
                      </li>
                      <li>
                        <button
                          className="block w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-gray-800 cursor-pointer"
                          onClick={() => {
                            openCategoryDeleteModal(category);
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
                          {document.sidebar_label}
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
                    {document.last_edited_by || '未設定'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span
                      className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                        document.is_public
                          ? 'bg-green-100 text-green-800'
                          : 'bg-red-100 text-red-800'
                      }`}
                    >
                      {document.is_public ? '公開' : '非公開'}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                    {document.file_order !== null ? document.file_order : '-'}
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
                            className="absolute right-0 top-6 w-30 bg-gray-900 border border-gray-700 rounded-md shadow-lg z-50"
                            onClick={e => e.stopPropagation()}
                          >
                            <ul className="py-1">
                              <li>
                                <Link
                                  to={`/documents/${pathAfterDocuments}/${document.slug}/edit`}
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
                                  onClick={() => openDeleteModal(document)}
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
    if (!pathAfterDocuments) return null;

    // スラグを/で分割して階層構造を作成
    const slugParts = pathAfterDocuments.split('/');
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
    <AdminLayout title={`${pathAfterDocuments || 'ドキュメント'} - ドキュメント管理`}>
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
                to={`/documents/create?category=${pathAfterDocuments}`}
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

      {/* ドキュメント削除確認モーダル */}
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

      {/* カテゴリ削除確認モーダル */}
      {showCategoryDeleteModal && categoryToDelete && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-gray-900 p-6 rounded-lg w-full max-w-md">
            <h2 className="text-xl font-bold mb-4">カテゴリを削除</h2>

            {categoryDeleteError && (
              <div className="mb-4 p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
                {categoryDeleteError}
              </div>
            )}

            <p className="mb-4 text-gray-300">
              「{categoryToDelete.sidebar_label || categoryToDelete.slug}」を削除しますか？
            </p>

            <div className="flex justify-end gap-2">
              <button
                className="px-4 py-2 bg-gray-800 rounded-md hover:bg-gray-700 focus:outline-none"
                onClick={closeCategoryDeleteModal}
                disabled={isCategoryDeleting}
              >
                キャンセル
              </button>
              <button
                className="px-4 py-2 bg-red-600 rounded-md hover:bg-red-700 focus:outline-none flex items-center"
                onClick={handleDeleteCategory}
                disabled={isCategoryDeleting}
              >
                {isCategoryDeleting ? (
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

      {/* カテゴリ編集モーダル */}
      {showCategoryEditModal && editingCategory && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-gray-900 p-6 rounded-lg w-full max-w-md">
            <h2 className="text-xl font-bold mb-4">カテゴリを編集</h2>

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
                  // 元のslugと同じ場合はバリデーションをスキップ
                  if (value !== editingCategory.slug) {
                    validateSlug(value);
                  } else {
                    setInvalidSlug(null);
                  }
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
                onClick={handleCloseEditModal}
              >
                キャンセル
              </button>
              <button
                className="px-4 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none flex items-center"
                onClick={handleEditCategory}
                disabled={!categorySlug.trim() || !label.trim() || isEditing}
              >
                {isEditing ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mr-2"></div>
                    <span>更新中...</span>
                  </>
                ) : (
                  <span>更新</span>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* トーストメッセージ */}
      {showToast && (
        <Toast message={toastMessage} type={toastType} onClose={() => setShowToast(false)} />
      )}
    </AdminLayout>
  );
}
