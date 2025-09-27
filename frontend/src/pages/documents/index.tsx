import AdminLayout, { type DocumentDetail } from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Breadcrumb } from '@/components/common/Breadcrumb';
import { Toast } from '@/components/admin/Toast';
import { fetchCategoryDetail, type ApiCategoryDetailResponse, type BreadcrumbItem } from '@/api/category';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { MarkdownRenderer } from '@/utils/markdownToHtml';
import { markdownStyles } from '@/styles/markdownContent';


/**
 * 管理画面のドキュメント一覧ページコンポーネント
 */
export default function DocumentsPage(): JSX.Element {
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType] = useState<'success' | 'error'>('success');
  const [selectedSideContentCategory, setSelectedSideContentCategory] = useState<string | null>(null);
  const [categoryDetail, setCategoryDetail] = useState<ApiCategoryDetailResponse | null>(null);
  const [selectedDocumentId, setSelectedDocumentId] = useState<number | null>(null);
  const [documentDetail, setDocumentDetail] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [showPrSubmitButton, setShowPrSubmitButton] = useState(false);
  const [userBranchId, setUserBranchId] = useState<string | null>(null);
  const [showDiffConfirmModal, setShowDiffConfirmModal] = useState(false);

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
      
      const endpoint = `/api/document-categories${params.toString() ? `?${params.toString()}` : ''}`;
      const response = await apiClient.get(endpoint);
      
      return response.categories || [];
    } catch (error) {
      console.error('カテゴリの取得に失敗しました:', error);
      throw error;
    }
  };

  // カテゴリ詳細を取得する関数
  const loadCategoryDetail = async (categoryId: number | string) => {
    try {
      setLoading(true);
      const detail = await fetchCategoryDetail(categoryId);
      console.log('categoryDetail', detail);
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
  const handleSideContentCategorySelect = (categoryId: number) => {
    setSelectedSideContentCategory(categoryId.toString());
    setSelectedDocumentId(null);
    setDocumentDetail(null);
    console.log('Selected side content category:', categoryId);
    loadCategoryDetail(categoryId);
  };

  // ドキュメント選択ハンドラ
  const handleDocumentSelect = async (documentId: number) => {
    try {
      setLoading(true);
      const endpoint = API_CONFIG.ENDPOINTS.DOCUMENT_VERSIONS.GET_DETAIL(documentId);
      console.log('API endpoint:', endpoint);
      console.log('Document ID:', documentId);
      
      const response = await apiClient.get(endpoint);
      
      console.log(response);
      // レスポンスからドキュメント詳細とパンクズリストを取得
      const documentDetail: DocumentDetail = {
        id: response.id,
        title: response.title,
        description: response.description,
        breadcrumbs: response.breadcrumbs
      };

      console.log(documentDetail);
      
      setSelectedDocumentId(documentDetail.id);
      setDocumentDetail(documentDetail);
      setSelectedSideContentCategory(null);
      setCategoryDetail(null);
      console.log('Selected document:', documentDetail);
      
    } catch (error) {
      console.error('ドキュメント取得エラー:', error);
      console.error('エラー詳細:', JSON.stringify(error, null, 2));
      if (error instanceof Error && (error as any).response) {
        console.error('レスポンス詳細:', (error as any).response);
      }
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
            // IDが最も小さいカテゴリを選択
            const minIdCategory = categories.reduce((min, current) => 
              current.id < min.id ? current : min
            );
            setSelectedSideContentCategory(minIdCategory.id.toString());
            await loadCategoryDetail(minIdCategory.id);
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
      selectedCategoryId={selectedSideContentCategory ? parseInt(selectedSideContentCategory) : undefined}
      selectedDocumentId={selectedDocumentId || undefined}
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

      {/* トーストメッセージ */}
      {showToast && (
        <Toast message={toastMessage} type={toastType} onClose={() => setShowToast(false)} />
      )}

    </AdminLayout>
  );
}
