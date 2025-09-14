import AdminLayout from '@/components/admin/layout';
import { useState } from 'react';
import type { JSX } from 'react';
import { Home } from '@/components/icon/common/Home';
import { Toast } from '@/components/admin/Toast';


/**
 * 管理画面のドキュメント一覧ページコンポーネント
 */
export default function DocumentsPage(): JSX.Element {
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType] = useState<'success' | 'error'>('success');
  const [selectedSideContentCategory, setSelectedSideContentCategory] = useState<string>('hr-system');

  // サイドコンテンツのカテゴリ選択ハンドラ
  const handleSideContentCategorySelect = (categoryId: number) => {
    setSelectedSideContentCategory(categoryId.toString());
    console.log('Selected side content category:', categoryId);
  };

  return (
    <AdminLayout 
      title="ドキュメント管理"
      sidebar={true}
      showDocumentSideContent={true}
      onCategorySelect={handleSideContentCategorySelect}
      selectedCategoryId={selectedSideContentCategory ? parseInt(selectedSideContentCategory) : undefined}
    >
        <div className="mb-6">
          {/* パンくずリスト */}
          <div className="flex items-center text-sm text-gray-400 mb-4">
            <a href="/documents" className="hover:text-white">
              <Home className="w-4 h-4 mx-2" />
            </a>
          </div>
        </div>

      {/* トーストメッセージ */}
      {showToast && (
        <Toast message={toastMessage} type={toastType} onClose={() => setShowToast(false)} />
      )}

    </AdminLayout>
  );
}
