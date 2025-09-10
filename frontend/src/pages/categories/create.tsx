import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import CategoryCreationForm, { useUnsavedChangesHandler } from '@/components/admin/CategoryCreationForm';
import AdminLayout from '@/components/admin/layout';
import UnsavedChangesModal from '@/components/admin/UnsavedChangesModal';

/**
 * ルートカテゴリ新規作成ページ
 * parent_id = null でカテゴリを作成
 */
export default function CreateRootCategoryPage(): JSX.Element {
  const navigate = useNavigate();
  const [selectedSideContentCategory, setSelectedSideContentCategory] = useState<number>(4);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  
  const {
    showModal,
    handleNavigationRequest,
    handleConfirm,
    handleCancel: handleModalCancel
  } = useUnsavedChangesHandler(hasUnsavedChanges);

  const handleSuccess = () => {
    // カテゴリ作成成功時はドキュメント一覧ページに遷移
    navigate('/documents', { 
      state: { 
        message: 'カテゴリが作成されました',
        type: 'success'
      }
    });
  };

  const handleCancel = () => {
    // キャンセル時はドキュメント一覧ページに遷移
    navigate('/documents');
  };

  // サイドバーのカテゴリ選択時に未保存変更をチェック
  const handleSideContentCategorySelect = (categoryId: number) => {
    handleNavigationRequest(() => {
      setSelectedSideContentCategory(categoryId);
    });
  };

  // AdminLayoutのナビゲーション用の制御された関数
  const handleControlledNavigation = (path: string) => {
    handleNavigationRequest(() => {
      window.location.href = path;
    });
  };

  return (
    <AdminLayout 
      title="カテゴリ作成"
      sidebar={true}
      showDocumentSideContent={true}
      onCategorySelect={handleSideContentCategorySelect}
      selectedCategoryId={selectedSideContentCategory}
      onNavigationRequest={handleControlledNavigation}
    >
      <CategoryCreationForm
        parentCategoryId={undefined} // parent_id = null でルートカテゴリを作成
        onSuccess={handleSuccess}
        onCancel={handleCancel}
        onUnsavedChangesChange={setHasUnsavedChanges}
      />

      {/* 未保存変更確認モーダル */}
      <UnsavedChangesModal
        isOpen={showModal}
        onConfirm={handleConfirm}
        onCancel={handleModalCancel}
      />
    </AdminLayout>
  );
}
