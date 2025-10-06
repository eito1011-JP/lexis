import AdminLayout, { type DocumentDetail } from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { Breadcrumb } from '@/components/common/Breadcrumb';
import { Toast } from '@/components/admin/Toast';
import { fetchCategoryDetail, type ApiCategoryDetailResponse, type BreadcrumbItem } from '@/api/category';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { MarkdownRenderer } from '@/utils/markdownToHtml';
import { markdownStyles } from '@/styles/markdownContent';
import { ROUTE_PATHS, ROUTES } from '@/routes';
import { useNavigate } from 'react-router-dom';


/**
 * 管理画面のドキュメント一覧ページコンポーネント
 */
export default function DocumentsPage(): JSX.Element {
  const navigate = useNavigate();
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType] = useState<'success' | 'error'>('success');
  const [selectedSideContentCategory, setSelectedSideContentCategory] = useState<number | null>(null);
  const [categoryDetail, setCategoryDetail] = useState<ApiCategoryDetailResponse | null>(null);
  const [selectedDocumentEntityId, setSelectedDocumentEntityId] = useState<number | null>(null);
  const [documentDetail, setDocumentDetail] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [showPrSubmitButton, setShowPrSubmitButton] = useState(false);
  const [userBranchId, setUserBranchId] = useState<string | null>(null);
  const [showDiffConfirmModal, setShowDiffConfirmModal] = useState(false);
  const [showDiscardConfirmModal, setShowDiscardConfirmModal] = useState(false);
  const [refreshTrigger, setRefreshTrigger] = useState(0);

  // マークダウンスタイルを一度だけ適用
  useEffect(() => {
    const styleId = 'markdown-content-styles';
    if (!document.getElementById(styleId)) {
      const style = document.createElement('style');
      style.id = styleId;
      style.textContent = markdownStyles;
      document.head.appendChild(style);
    }
  }, []);

  // カテゴリリストを取得する関数
  const fetchCategories = async (parentId: number | null = null): Promise<any[]> => {
    try {
      const params = new URLSearchParams();
      if (parentId !== null) {
        params.append('parent_entity_id', parentId.toString());
      }
      
      const endpoint = `/api/category-entities/${params.toString() ? `?${params.toString()}` : ''}`;
      const response = await apiClient.get(endpoint);
      
      return response.categories || [];
    } catch (error) {
      console.error('カテゴリの取得に失敗しました:', error);
      throw error;
    }
  };

  // カテゴリ詳細を取得する関数
  const loadCategoryDetail = async (categoryEntityId: number) => {
    try {
      setLoading(true);
      const detail = await fetchCategoryDetail(categoryEntityId);
      setCategoryDetail(detail);
    } catch (error) {
      console.error('カテゴリ詳細の取得に失敗しました:', error);
      setToastMessage('カテゴリ詳細の取得に失敗しました');
      setToastType('error');
      setShowToast(true);
    } finally {
      setLoading(false);
    }
  };

  // サイドコンテンツのカテゴリ選択ハンドラ
  const handleSideContentCategorySelect = (categoryEntityId: number) => {
    setSelectedSideContentCategory(categoryEntityId);
    setSelectedDocumentEntityId(null);
    setDocumentDetail(null);
    console.log('Selected side content category:', categoryEntityId);
    loadCategoryDetail(categoryEntityId);
  };

  // ドキュメント選択ハンドラ
  const handleDocumentSelect = async (documentEntityId: number) => {
    try {
      setLoading(true);
      const endpoint = API_CONFIG.ENDPOINTS.DOCUMENT_VERSIONS.GET_DETAIL(documentEntityId);
      
      const response = await apiClient.get(endpoint);
      
      // レスポンスからドキュメント詳細とパンクズリストを取得
      const documentDetail: DocumentDetail = {
        entityId: response.entityId,
        title: response.title,
        description: response.description,
        breadcrumbs: response.breadcrumbs
      };

      setSelectedDocumentEntityId(documentDetail.entityId);
      setDocumentDetail(documentDetail);
      setSelectedSideContentCategory(null);
      setCategoryDetail(null);
      
    } catch (error) {
      console.error('ドキュメント取得エラー:', error);
      setToastMessage('ドキュメント詳細の取得に失敗しました');
      setToastType('error');
      setShowToast(true);
    } finally {
      setLoading(false);
    }
  };

  // 初期表示時に最小IDのカテゴリを自動選択とユーザー変更チェック
  useEffect(() => {
    const loadInitialCategory = async () => {
      try {
        if (!selectedSideContentCategory) {
          // カテゴリリストを取得
          const categories = await fetchCategories(null);
          if (categories.length > 0) {
            // entity_IDが最も小さいカテゴリを選択
            const minIdCategory = categories.reduce((min, current) => 
              current.entity_id < min.entity_id ? current : min
            );
            setSelectedSideContentCategory(minIdCategory.entity_id);
            await loadCategoryDetail(minIdCategory.entity_id);
          }
        } else {
          await loadCategoryDetail(selectedSideContentCategory);
        }

        // ユーザー変更があるかチェック
        const hasUserChanges = await apiClient.get(
          API_CONFIG.ENDPOINTS.USER_BRANCHES.HAS_USER_CHANGES
        );
        if (hasUserChanges && hasUserChanges.has_user_changes) {
          setShowPrSubmitButton(true);
          setUserBranchId(hasUserChanges.user_branch_id);
        }
      } catch (error) {
        console.error('初期カテゴリの読み込みに失敗しました:', error);
        setToastMessage('初期カテゴリの読み込みに失敗しました');
        setToastType('error');
        setShowToast(true);
      }
    };

    loadInitialCategory();
  }, []);

  return (
    <AdminLayout 
      title="ドキュメント管理"
      sidebar={true}
      showDocumentSideContent={true}
      onCategorySelect={handleSideContentCategorySelect}
      onDocumentSelect={handleDocumentSelect}
      selectedCategoryEntityId={selectedSideContentCategory ? selectedSideContentCategory : undefined}
      selectedDocumentEntityId={selectedDocumentEntityId || undefined}
      refreshTrigger={refreshTrigger}
    >
         <div className="mb-6 align-left">
           {/* パンくずリストと差分提出ボタン */}
           <div className="flex items-center justify-between mb-6 mx-6 mx-auto">
            <Breadcrumb 
              breadcrumbs={
                documentDetail?.breadcrumbs || 
                (categoryDetail?.breadcrumbs ? categoryDetail.breadcrumbs : undefined)
              } 
            />

            <div className="flex items-center gap-4 mr-9">
              <button
                className="flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                onClick={() => {
                  setShowDiscardConfirmModal(true);
                }}
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
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                  ></path>
                </svg>
                <span>差分破棄</span>
              </button>
              <button
                className="flex items-center px-3 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                onClick={() => {
                  setShowDiffConfirmModal(true);
                }}
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
            </div>
          </div>
        </div>

        {/* カテゴリ・ドキュメント詳細コンテンツ */}
        <div className="max-w-4xl">
          {loading ? (
            <div className="flex items-center justify-center py-8">
              <div className="text-gray-400">読み込み中...</div>
            </div>
          ) : documentDetail ? (
            <div className="space-y-6">
              {/* ドキュメント詳細セクション */}
              <div className="space-y-4">
                <h2 className="text-xl font-semibold text-white">
                  {documentDetail.title}
                </h2>
                <div className="text-gray-300 leading-relaxed max-w-none markdown-content">
                  <MarkdownRenderer>
                    {documentDetail.description || ''}
                  </MarkdownRenderer>
                </div>
              </div>
            </div>
          ) : categoryDetail ? (
            <div className="space-y-6">
              {/* カテゴリ詳細セクション */}
              <div className="space-y-4">
                <h2 className="text-xl font-semibold text-white">
                  {categoryDetail.title}
                </h2>
                <div className="text-gray-300 leading-relaxed max-w-none markdown-content">
                  <MarkdownRenderer>
                    {categoryDetail.description || ''}
                  </MarkdownRenderer>
                </div>
              </div>
            </div>
          ) : (
            <div className="text-center py-8">
              <p className="text-gray-400">カテゴリまたはドキュメントを選択してください</p>
            </div>
          )}
        </div>

      {/* 差分提出確認モーダル */}
      {showDiffConfirmModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-gray-900 p-6 rounded-lg w-full max-w-md">
            <h2 className="text-xl font-bold mb-4">変更内容を提出</h2>

            <p className="mb-4 text-gray-300">
              作成した変更内容をレビュー用に提出します。よろしいですか？
            </p>

            <div className="flex justify-end gap-2">
              <button
                className="px-4 py-2 bg-gray-800 rounded-md hover:bg-gray-700 focus:outline-none"
                onClick={() => setShowDiffConfirmModal(false)}
              >
                キャンセル
              </button>
              <button
                className="px-4 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none flex items-center"
                onClick={() => {
                  if (userBranchId) {
                    window.location.href = `/documents/diff?user_branch_id=${userBranchId}`;
                  } else {
                    // user_branch_idが取得できない場合はエラーメッセージを表示
                    setToastMessage(
                      '差分データの取得に失敗しました。ページを再読み込みしてください。'
                    );
                    setToastType('error');
                    setShowToast(true);
                    setShowDiffConfirmModal(false);
                  }
                }}
              >
                差分確認画面へ
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 差分破棄確認モーダル */}
      {showDiscardConfirmModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-gray-900 p-6 rounded-lg w-full max-w-md">
            <h2 className="text-xl font-bold mb-4">差分を破棄</h2>

            <p className="mb-4 text-gray-300">
              差分を下書きとして保存しますか？
            </p>

            <div className="flex justify-end gap-2">
              <button
                className="px-4 py-2 bg-gray-800 rounded-md hover:bg-gray-700 focus:outline-none"
                onClick={() => setShowDiscardConfirmModal(false)}
              >
                キャンセル
              </button>
              <button
                className="px-4 py-2 bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none"
                onClick={() => {
                  // TODO: 下書きとして保存する処理を実装
                  setToastMessage('下書き保存機能は未実装です');
                  setToastType('error');
                  setShowToast(true);
                  setShowDiscardConfirmModal(false);
                }}
              >
                はい
              </button>
              <button
                className="px-4 py-2 bg-red-600 rounded-md hover:bg-red-700 focus:outline-none"
                onClick={async () => {
                  if (!userBranchId) {
                    setToastMessage(
                      '差分データの取得に失敗しました。ページを再読み込みしてください。'
                    );
                    setToastType('error');
                    setShowToast(true);
                    setShowDiscardConfirmModal(false);
                    return;
                  }

                  try {
                    await apiClient.delete(`/api/user-branches/${userBranchId}`);
                    setToastMessage('差分を破棄しました');
                    setToastType('success');
                    setShowToast(true);
                    setShowDiscardConfirmModal(false);
                    setShowPrSubmitButton(false);
                    setUserBranchId(null);
                    
                    // サイドバーのカテゴリを最新情報に更新
                    setRefreshTrigger(prev => prev + 1);
                    
                    // ドキュメント詳細とカテゴリ詳細をクリア（古い情報を表示しないように）
                    setSelectedDocumentEntityId(null);
                    setDocumentDetail(null);
                    setSelectedSideContentCategory(null);
                    setCategoryDetail(null);
                    
                    // 最新のカテゴリ情報を再取得
                    const categories = await fetchCategories(null);
                    if (categories.length > 0) {
                      const minIdCategory = categories.reduce((min, current) => 
                        current.entity_id < min.entity_id ? current : min
                      );
                      setSelectedSideContentCategory(minIdCategory.entity_id);
                      await loadCategoryDetail(minIdCategory.entity_id);
                    }
                    
                  } catch (error) {
                    console.error('差分破棄エラー:', error);
                    setToastMessage('差分の破棄に失敗しました');
                    setToastType('error');
                    setShowToast(true);
                    setShowDiscardConfirmModal(false);
                  }
                }}
              >
                いいえ
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
