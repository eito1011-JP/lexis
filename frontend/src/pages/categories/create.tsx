import React, { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import CategoryForm, { useUnsavedChangesHandler, CategoryFormData } from '@/components/admin/CategoryForm';
import AdminLayout from '@/components/admin/layout';
import UnsavedChangesModal from '@/components/admin/UnsavedChangesModal';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';

/**
 * カテゴリ新規作成ページ
 * URLパスに応じてルートカテゴリまたはサブカテゴリを作成
 * - /categories/create: parent_entity_id = null (ルートカテゴリ)
 * - /categories/:categoryId/create: parent_entity_id = :categoryId (サブカテゴリ)
 */
export default function CreateRootCategoryPage(): JSX.Element {
  const navigate = useNavigate();
  const { categoryEntityId } = useParams<{ categoryEntityId: string }>();
  const [selectedSideContentCategory, setSelectedSideContentCategory] = useState<number>(4);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  
  const {
    showModal,
    handleNavigationRequest,
    handleConfirm,
    handleCancel: handleModalCancel
  } = useUnsavedChangesHandler(hasUnsavedChanges);

  const handleSubmit = async (formData: CategoryFormData) => {
    setIsSubmitting(true);
    setError(null);

    try {
      // URLパラメータに基づいてparent_entity_idを設定
      const parentEntityId = categoryEntityId ? categoryEntityId : null;

      const payload = {
        title: formData.title,
        description: formData.description,
        parent_entity_id: parentEntityId
      };

      await apiClient.post(API_CONFIG.ENDPOINTS.CATEGORIES.CREATE, payload);
      
      // カテゴリ作成成功時はドキュメント一覧ページに遷移
      navigate('/documents', { 
        state: { 
          message: 'カテゴリが作成されました',
          type: 'success'
        }
      });
    } catch (error: any) {
      console.error('カテゴリの作成に失敗しました:', error);
      if (error.response?.data?.message) {
        setError(error.response.data.message);
      } else {
        setError('カテゴリの作成に失敗しました');
      }
    } finally {
      setIsSubmitting(false);
    }
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
      selectedCategoryEntityId={selectedSideContentCategory}
      onNavigationRequest={handleControlledNavigation}
    >
      <CategoryForm
        onSubmit={handleSubmit}
        onCancel={handleCancel}
        onUnsavedChangesChange={setHasUnsavedChanges}
        isSubmitting={isSubmitting}
        submitButtonText="作成"
        submittingText="作成中..."
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
