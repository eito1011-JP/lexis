import AdminLayout, { type DocumentDetail } from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { Breadcrumb } from '@/components/common/Breadcrumb';
import { Toast } from '@/components/admin/Toast';
import { MarkdownRenderer } from '@/utils/markdownToHtml';
import { markdownStyles } from '@/styles/markdownContent';
import { useNavigate } from 'react-router-dom';
import { useUserMe } from '@/hooks/useUserMe';
import { useCategories } from '@/hooks/useCategories';
import { useCategoryDetail } from '@/hooks/useCategoryDetail';
import { useDocumentDetail } from '@/hooks/useDocumentDetail';
import { useUserBranchChanges } from '@/hooks/useUserBranchChanges';
import { client } from '@/api/client';


/**
 * 管理画面のドキュメント一覧ページコンポーネント
 */
export default function DocumentsPage(): JSX.Element {
  const navigate = useNavigate();
  const { mutate: mutateUserMe } = useUserMe();
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType] = useState<'success' | 'error'>('success');
  const [selectedSideContentCategory, setSelectedSideContentCategory] = useState<number | null>(null);
  const [selectedDocumentEntityId, setSelectedDocumentEntityId] = useState<number | null>(null);
  const [showDiffConfirmModal, setShowDiffConfirmModal] = useState(false);
  const [showDiscardConfirmModal, setShowDiscardConfirmModal] = useState(false);
  const [refreshTrigger, setRefreshTrigger] = useState(0);

  // カスタムフックの使用
  const { categories, isLoading: categoriesLoading } = useCategories(null);
  const { categoryDetail, isLoading: categoryLoading } = useCategoryDetail(selectedSideContentCategory);
  const { documentDetail: docDetail, isLoading: documentLoading } = useDocumentDetail(selectedDocumentEntityId);
  const { hasUserChanges, userBranchId, mutate: mutateUserBranchChanges } = useUserBranchChanges();

  // ローディング状態の統合
  const loading = categoriesLoading || categoryLoading || documentLoading;
  
  // ドキュメント詳細をローカルstateに変換（パンくずリスト用）
  const documentDetail: DocumentDetail | null = docDetail ? ({
    entityId: docDetail.document.entityId,
    title: docDetail.document.title,
    description: docDetail.document.description ?? '',
    breadcrumbs: docDetail.document.breadcrumbs
  } as DocumentDetail) : null;

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

  // サイドコンテンツのカテゴリ選択ハンドラ
  const handleSideContentCategorySelect = (categoryEntityId: number) => {
    setSelectedSideContentCategory(categoryEntityId);
    setSelectedDocumentEntityId(null);
    console.log('Selected side content category:', categoryEntityId);
  };

  // ドキュメント選択ハンドラ
  const handleDocumentSelect = (documentEntityId: number) => {
    setSelectedDocumentEntityId(documentEntityId);
    setSelectedSideContentCategory(null);
  };

  // 下書き保存のハンドラー
  const handleDraftSave = async () => {
    if (!userBranchId) {
      setToastMessage('ユーザーブランチIDが取得できません');
      setToastType('error');
      setShowToast(true);
      return;
    }

    try {
      await client.user_branches._userBranchId(parseInt(userBranchId)).$put({
        body: {
          is_active: false,
          user_branch_id: userBranchId
        }
      });

      setToastMessage('下書きを保存しました');
      setToastType('success');
      setShowToast(true);
      
      // ユーザー情報を再取得
      await mutateUserMe();
      await mutateUserBranchChanges();
      
      setShowDiscardConfirmModal(false);
    } catch (error) {
      console.error('下書き保存エラー:', error);
      setToastMessage('下書きの保存に失敗しました');
      setToastType('error');
      setShowToast(true);
    }
  };

  // 初期表示時に最小IDのカテゴリを自動選択
  useEffect(() => {
    if (!selectedSideContentCategory && categories.length > 0) {
      // entity_IDが最も小さいカテゴリを選択
      const minIdCategory = categories.reduce((min, current) => 
        current.entity_id < min.entity_id ? current : min
      );
      setSelectedSideContentCategory(minIdCategory.entity_id);
    }
  }, [categories, selectedSideContentCategory]);

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
                className="flex items-center px-3 py-2 bg-[#B1B1B1] hover:bg-[#B1B1B1] text-white rounded-md focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                onClick={() => {
                  setShowDiscardConfirmModal(true);
                }}
                disabled={!hasUserChanges}
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
                <span>下書き保存</span>
              </button>
              <button
                className="flex items-center px-3 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
                onClick={() => {
                  setShowDiffConfirmModal(true);
                }}
                disabled={!hasUserChanges}
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
            </div>
          )}
        </div>

      {/* 下書き保存確認モーダル */}
      {showDiscardConfirmModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-gray-900 p-6 rounded-lg w-full max-w-md">
            <h2 className="text-xl font-bold mb-4">下書き保存</h2>

            <p className="mb-4 text-gray-300">
              現在の変更内容を下書きとして保存します。よろしいですか？
            </p>

            <div className="flex justify-end gap-2">
              <button
                className="px-4 py-2 bg-gray-800 rounded-md hover:bg-gray-700 focus:outline-none"
                onClick={() => setShowDiscardConfirmModal(false)}
              >
                キャンセル
              </button>
              <button
                className="px-4 py-2 bg-[#B1B1B1] rounded-md hover:bg-[#8A8A8A] focus:outline-none flex items-center"
                onClick={handleDraftSave}
              >
                下書き保存
              </button>
            </div>
          </div>
        </div>
      )}

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
