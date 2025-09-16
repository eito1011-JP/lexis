import React, { useState, useRef } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import CategoryForm, { useUnsavedChangesHandler, CategoryFormData } from '@/components/admin/CategoryForm';
import AdminLayout from '@/components/admin/layout';
import UnsavedChangesModal from '@/components/admin/UnsavedChangesModal';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';

/**
 * カテゴリ新規作成ページ
 * /documents/categories/create?parent_id=xxx でアクセス
 */
export default function CreateCategoryPage(): JSX.Element {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const parentId = searchParams.get('parent_id');
  const [selectedSideContentCategory, setSelectedSideContentCategory] = useState<number>(4);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // 未保存変更ハンドラーを使用
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
      const payload = {
        title: formData.title,
        description: formData.description,
        parent_id: parentId ? parseInt(parentId) : null,
        edit_pull_request_id: null,
        pull_request_edit_token: null,
      };

      await apiClient.post(API_CONFIG.ENDPOINTS.CATEGORIES.CREATE, payload);
      
      // 成功時はドキュメント一覧ページに戻る
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
    // キャンセル時はドキュメント一覧ページに戻る
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
