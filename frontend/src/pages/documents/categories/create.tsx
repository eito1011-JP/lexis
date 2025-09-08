import React, { useState, useRef } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import CategoryCreationForm, { useUnsavedChangesHandler } from '@/components/admin/CategoryCreationForm';
import AdminLayout from '@/components/admin/layout';
import UnsavedChangesModal from '@/components/admin/UnsavedChangesModal';

/**
 * カテゴリ新規作成ページ
 * /documents/categories/create?parent_id=xxx でアクセス
 */
export default function CreateCategoryPage(): JSX.Element {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [selectedSideContentCategory, setSelectedSideContentCategory] = useState<string>('hr-system');
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  
  const parentCategoryId = searchParams.get('parent_id') || '';
  const parentCategoryPath = searchParams.get('parent_path') || '';

  // 未保存変更ハンドラーを使用
  const {
    showModal,
    handleNavigationRequest,
    handleConfirm,
    handleCancel: handleModalCancel
  } = useUnsavedChangesHandler(hasUnsavedChanges);

  const handleSuccess = (newCategory: any) => {
    // 成功時はドキュメント一覧ページに戻る
    navigate('/documents', { 
      state: { 
        message: 'カテゴリが作成されました',
        type: 'success'
      }
    });
  };

  const handleCancel = () => {
    // キャンセル時はドキュメント一覧ページに戻る
    navigate('/documents');
  };

  // サイドバーのカテゴリ選択時に未保存変更をチェック
  const handleSideContentCategorySelect = (categoryId: string) => {
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
        parentCategoryId={parentCategoryId}
        parentCategoryPath={parentCategoryPath}
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
