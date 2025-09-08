import React, { useState } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import CategoryCreationForm from '@/components/admin/CategoryCreationForm';
import AdminLayout from '@/components/admin/layout';

/**
 * カテゴリ新規作成ページ
 * /documents/categories/create?parent_id=xxx でアクセス
 */
export default function CreateCategoryPage(): JSX.Element {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [selectedSideContentCategory, setSelectedSideContentCategory] = useState<string>('hr-system');
  
  const parentCategoryId = searchParams.get('parent_id') || '';
  const parentCategoryPath = searchParams.get('parent_path') || '';

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

  const handleSideContentCategorySelect = (categoryId: string) => {
    setSelectedSideContentCategory(categoryId);
  };

  return (
    <AdminLayout 
      title="カテゴリ作成"
      sidebar={true}
      showDocumentSideContent={true}
      onCategorySelect={handleSideContentCategorySelect}
      selectedCategoryId={selectedSideContentCategory}
    >
      <CategoryCreationForm
        parentCategoryId={parentCategoryId}
        parentCategoryPath={parentCategoryPath}
        onSuccess={handleSuccess}
        onCancel={handleCancel}
      />
    </AdminLayout>
  );
}
