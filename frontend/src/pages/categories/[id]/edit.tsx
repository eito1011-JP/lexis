import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import CategoryCreationForm, { useUnsavedChangesHandler } from '@/components/admin/CategoryCreationForm';
import AdminLayout from '@/components/admin/layout';
import UnsavedChangesModal from '@/components/admin/UnsavedChangesModal';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';

/**
 * カテゴリ編集ページ
 * /categories/[id]/edit でアクセス
 */
export default function EditCategoryPage(): JSX.Element {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [selectedSideContentCategory, setSelectedSideContentCategory] = useState<number>(4);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [category, setCategory] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);

  const {
    showModal,
    handleNavigationRequest,
    handleConfirm,
    handleCancel: handleModalCancel
  } = useUnsavedChangesHandler(hasUnsavedChanges);

  // カテゴリデータを取得
  useEffect(() => {
    const fetchCategory = async () => {
      if (!id) return;
      
      try {
        setIsLoading(true);
        const response = await apiClient.get(`${API_CONFIG.ENDPOINTS.CATEGORIES.GET_DETAIL}/${id}`);
        console.log(response);
        setCategory(response.category);
      } catch (err) {
        console.error('カテゴリの取得に失敗しました:', err);
        setError('カテゴリの取得に失敗しました');
      } finally {
        setIsLoading(false);
      }
    };

    fetchCategory();
  }, [id]);

  const handleSave = async () => {
    console.log('categoryddd', category);
    try {
      await apiClient.put(`${API_CONFIG.ENDPOINTS.CATEGORIES.UPDATE}/${id}`, {
        title: category.title,
        description: category.description,
      });
      // カテゴリ編集成功時はドキュメント一覧ページに遷移
      navigate('/documents', { 
        state: { 
          message: 'カテゴリが更新されました',
          type: 'success'
        }
      });
    } catch (error) {
      console.error('カテゴリの更新に失敗しました:', error);
      setError('カテゴリの更新に失敗しました');
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

  if (isLoading) {
    return (
      <AdminLayout 
        title="カテゴリ編集"
        sidebar={true}
        showDocumentSideContent={true}
        onCategorySelect={handleSideContentCategorySelect}
        selectedCategoryId={selectedSideContentCategory}
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
        selectedCategoryId={selectedSideContentCategory}
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
      selectedCategoryId={selectedSideContentCategory}
      onNavigationRequest={handleControlledNavigation}
    >
      <CategoryCreationForm
        parentCategoryId={category.parent_id}
        initialData={{
          slug: category.slug,
          title: category.title,
          description: category.description || '',
          position: category.position || ''
        }}
        isEditMode={true}
        categoryId={parseInt(id!)}
        onSuccess={handleSave}
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
