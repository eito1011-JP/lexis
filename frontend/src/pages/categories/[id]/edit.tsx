import React, { useState, useEffect, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import CategoryForm, { useUnsavedChangesHandler, CategoryFormData } from '@/components/admin/CategoryForm';
import AdminLayout from '@/components/admin/layout';
import UnsavedChangesModal from '@/components/admin/UnsavedChangesModal';
import { client } from '@/api/client';
import { Breadcrumb } from '@/components/common/Breadcrumb';

/**
 * カテゴリ編集ページ
 * /categories/[entity_id]/edit でアクセス
 * URLパラメータのidはdocument_category_entities.idを指す
 */
export default function EditCategoryPage(): JSX.Element {
  const { categoryEntityId } = useParams<{ categoryEntityId: string }>();
  const navigate = useNavigate();
  const [selectedSideContentCategory, setSelectedSideContentCategory] = useState<number>(4);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [category, setCategory] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [categoryBreadcrumbs, setCategoryBreadcrumbs] = useState<Array<{ id: number; title: string; }>>([]);

  const {
    showModal,
    handleNavigationRequest,
    handleConfirm,
    handleCancel: handleModalCancel
  } = useUnsavedChangesHandler(hasUnsavedChanges);

  // カテゴリデータを取得
  useEffect(() => {
    const fetchCategory = async () => {
      if (!categoryEntityId) return;
      
      try {
        setIsLoading(true);
        const response = await client.category_entities._entityId(parseInt(categoryEntityId)).$get();
        setCategory(response.category);
        setCategoryBreadcrumbs(response.category.breadcrumbs || []);
      } catch (err) {
        console.error('カテゴリの取得に失敗しました:', err);
        setError('カテゴリの取得に失敗しました');
      } finally {
        setIsLoading(false);
      }
    };

    fetchCategory();
  }, [categoryEntityId]);

  const handleSubmit = async (formData: CategoryFormData) => {
    if (!categoryEntityId) return;
    
    setIsSubmitting(true);
    setError(null);

    try {
      await client.category_entities._entityId(parseInt(categoryEntityId)).$put({
        body: {
          title: formData.title,
          description: formData.description,
        }
      });
      
      // カテゴリ編集成功時はドキュメント一覧ページに遷移
      navigate('/documents', { 
        state: { 
          message: 'カテゴリが更新されました',
          type: 'success'
        }
      });
    } catch (error: any) {
      console.error('カテゴリの更新に失敗しました:', error);
      if (error.response?.data?.message) {
        setError(error.response.data.message);
      } else {
        setError('カテゴリの更新に失敗しました');
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

  // initialDataをメモ化して不要な再レンダリングを防ぐ
  const initialData = useMemo(() => ({
    title: category?.title || '',
    description: category?.description || ''
  }), [category]);

  if (isLoading) {
    return (
      <AdminLayout 
        title="カテゴリ編集"
        sidebar={true}
        showDocumentSideContent={true}
        onCategorySelect={handleSideContentCategorySelect}
        selectedCategoryEntityId={selectedSideContentCategory}
        onNavigationRequest={handleControlledNavigation}
      >
        <div className="flex justify-center items-center h-64">
          <div className="text-gray-400">読み込み中...</div>
        </div>
      </AdminLayout>
    );
  }

  if (error || !category) {
    return (
      <AdminLayout 
        title="カテゴリ編集"
        sidebar={true}
        showDocumentSideContent={true}
        onCategorySelect={handleSideContentCategorySelect}
        selectedCategoryEntityId={selectedSideContentCategory}
        onNavigationRequest={handleControlledNavigation}
      >
        <div className="flex justify-center items-center h-64">
          <div className="text-red-400">{error || 'カテゴリが見つかりません'}</div>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout 
      title="カテゴリ編集"
      sidebar={true}
      showDocumentSideContent={true}
      onCategorySelect={handleSideContentCategorySelect}
      selectedCategoryEntityId={selectedSideContentCategory}
      onNavigationRequest={handleControlledNavigation}
    >
      <div className="text-white">
        {/* パンくずリスト */}
        <div className="mb-6 p-6 border-b border-gray-700">
          <Breadcrumb breadcrumbs={categoryBreadcrumbs} />
        </div>
        
        <CategoryForm
          initialData={initialData}
          onSubmit={handleSubmit}
          onCancel={handleCancel}
          onUnsavedChangesChange={setHasUnsavedChanges}
          isSubmitting={isSubmitting}
          submitButtonText="更新"
          submittingText="更新中..."
        />
      </div>

      {/* 未保存変更確認モーダル */}
      <UnsavedChangesModal
        isOpen={showModal}
        onConfirm={handleConfirm}
        onCancel={handleModalCancel}
      />
    </AdminLayout>
  );
}
