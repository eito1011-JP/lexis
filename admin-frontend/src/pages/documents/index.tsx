import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { apiClient } from '@/components/admin/api/client';
import { MultipleFolder } from '@/components/icon/common/MultipleFolder';
import { Folder } from '@/components/icon/common/Folder';
import { API_CONFIG } from '@/components/admin/api/config';
import { Home } from '@/components/icon/common/Home';
// カテゴリの型定義
type Category = {
  name: string;
  slug: string;
};

// ドキュメントアイテムの型定義
type DocumentItem = {
  type: string;
  label: string;
  name?: string;
  slug: string;
  status?: string;
  lastEditedBy?: string;
  content?: string;
};

/**
 * 管理画面のドキュメント一覧ページコンポーネント
 */
export default function DocumentsPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/login', false);

  const [showCategoryModal, setShowCategoryModal] = useState(false);
  const [slug, setSlug] = useState('');
  const [label, setLabel] = useState('');
  const [position, setPosition] = useState('');
  const [description, setDescription] = useState('');
  const [isCreating, setIsCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [documents, setDocuments] = useState<DocumentItem[]>([]);
  const [categoriesLoading, setCategoriesLoading] = useState(true);
  const [documentsLoading, setDocumentsLoading] = useState(true);
  const [apiError, setApiError] = useState<string | null>(null);
  const [showSubmitButton, setShowSubmitButton] = useState(false);
  const [showSubmitModal, setShowSubmitModal] = useState(false);
  const [showPrSubmitButton, setShowPrSubmitButton] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState<string | null>(null);
  const [invalidSlug, setInvalidSlug] = useState<string | null>(null);

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
    // カテゴリ一覧を取得
    const getDocuments = async () => {
      try {
        let categoryData = [];
        let documentData = [];
        const documents = await apiClient.get(API_CONFIG.ENDPOINTS.DOCUMENTS.GET_DOCUMENT);

        // documentsデータからカテゴリー（フォルダ）のデータを取得して設定
        if (documents && documents.items) {
          const categoryItems = documents.items.filter(
            (item: DocumentItem) => item.type === 'folder'
          );
          categoryData = categoryItems.map((category: DocumentItem) => ({
            name: category.label || '',
            slug: category.slug,
          }));
          setCategories(categoryData);

          // ドキュメントのデータを取得して設定
          const docItems = documents.items.filter((item: DocumentItem) => item.type === 'file');
          setDocuments(docItems);
        }

        const hasUserDraft = await apiClient.get(API_CONFIG.ENDPOINTS.GIT.CHECK_DIFF);
        if (hasUserDraft && hasUserDraft.exists) {
          setShowPrSubmitButton(true);
        }

        console.log('hasUserDraft', hasUserDraft);
      } catch (err) {
        console.error('カテゴリ取得エラー:', err);
      } finally {
        setCategoriesLoading(false);
        setDocumentsLoading(false);
      }
    };

    getDocuments();
  }, []);

  const handleCreateImageCategory = () => {
    setShowCategoryModal(true);
  };

  const handleCloseModal = () => {
    setShowCategoryModal(false);
    setSlug('');
    setLabel('');
    setPosition('');
    setDescription('');
    setInvalidSlug(null);
  };

  const handleCreateCategory = async () => {
    if (!slug.trim()) return;

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
        slug,
        label,
        position: positionNum,
        description,
      });

      // 新しいカテゴリをリストに追加
      if (response.slug) {
        setCategories(prev => [...prev, { name: response.label, slug: response.slug }]);
      }
      handleCloseModal();
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
      const response = await apiClient.post(API_CONFIG.ENDPOINTS.GIT.CREATE_PR, {
        title: '更新内容の提出',
        description: 'このPRはハンドブックの更新を含みます。',
      });

      if (response.success) {
        setShowSubmitModal(false);
        setShowSubmitButton(false);
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
  const renderCategorySection = () => {
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
            className="flex items-center p-3 bg-gray-900 rounded-md border border-gray-800 hover:bg-gray-800 cursor-pointer"
            onClick={() => (window.location.href = `/admin/documents/${category.slug}`)}
          >
            <Folder className="w-5 h-5 mr-2" />
            <span>{category.name}</span>
          </div>
        ))}
      </div>
    );
  };

  // ドキュメント一覧テーブル
  const renderDocumentTable = () => {
    console.log(documents);
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
                コンテンツ
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                公開ステータス
              </th>
              <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                最終編集者
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
                          {document.label || document.name || document.slug}
                        </div>
                        <div className="text-sm text-gray-400">{document.slug}</div>
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm text-gray-300">
                      {document.content
                        ? document.content.substring(0, 50) + '...'
                        : 'コンテンツなし'}
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span
                      className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                        document.status === '公開'
                          ? 'bg-green-100 text-green-800'
                          : 'bg-yellow-100 text-yellow-800'
                      }`}
                    >
                      {document.status || '非公開'}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                    {document.lastEditedBy || 'eito-morohashi@nexis-inc.com'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <a
                      href={`documents/${document.slug}/edit`}
                      className="text-indigo-400 hover:text-indigo-300 mr-4"
                    >
                      編集
                    </a>
                    <span className="text-gray-500">・・・</span>
                  </td>
                </tr>
              ))}
            </tbody>
          ) : (
            <tbody className="divide-y">
              <tr>
                <td colSpan={5} className="text-gray-400 py-4">
                  ドキュメントがありません
                </td>
              </tr>
            </tbody>
          )}
        </table>
      </div>
    );
  };

  return (
    <AdminLayout title="ドキュメント管理">
      <div className="flex flex-col h-full">
        <div className="mb-6">
          {/* パンくずリスト */}
          <div className="flex items-center text-sm text-gray-400 mb-4">
            <a href="/admin" className="hover:text-white">
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

              <button
                className="flex items-center px-3 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none"
                onClick={() => (window.location.href = '/admin/documents/create')}
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
              </button>

              <button
                className="flex items-center px-3 py-2 bg-gray-700 rounded-md hover:bg-gray-600 focus:outline-none"
                onClick={handleCreateImageCategory}
              >
                <MultipleFolder className="w-5 h-5 mr-2" />
                <span>新規カテゴリ</span>
              </button>
            </div>
          </div>

          {/* ドキュメント一覧テーブル */}
          <div className="mb-8">{renderDocumentTable()}</div>

          {/* カテゴリセクション */}
          <div className="mb-8">
            <h2 className="text-xl font-bold mb-4">カテゴリ</h2>
            {renderCategorySection()}
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
                value={slug}
                onChange={e => {
                  const value = e.target.value;
                  setSlug(value);
                  validateSlug(value);
                }}
              />
              {invalidSlug && (
                <p className="text-red-500 text-xs mt-1 mb-2">{invalidSlug}</p>
              )}
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
                onClick={handleCreateCategory}
                disabled={!slug.trim() || !label.trim() || isCreating}
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
