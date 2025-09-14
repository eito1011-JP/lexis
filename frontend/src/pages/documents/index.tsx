import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { Home } from '@/components/icon/common/Home';
import { Toast } from '@/components/admin/Toast';
import { fetchCategoryDetail, type ApiCategoryDetailResponse } from '@/api/category';
import { apiClient } from '@/components/admin/api/client';


/**
 * 管理画面のドキュメント一覧ページコンポーネント
 */
export default function DocumentsPage(): JSX.Element {
  const [showToast, setShowToast] = useState(false);
  const [toastMessage, setToastMessage] = useState('');
  const [toastType, setToastType] = useState<'success' | 'error'>('success');
  const [selectedSideContentCategory, setSelectedSideContentCategory] = useState<string | null>(null);
  const [categoryDetail, setCategoryDetail] = useState<ApiCategoryDetailResponse | null>(null);
  const [loading, setLoading] = useState(false);

  // カテゴリリストを取得する関数
  const fetchCategories = async (parentId: number | null = null): Promise<any[]> => {
    try {
      const params = new URLSearchParams();
      if (parentId !== null) {
        params.append('parent_id', parentId.toString());
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
    console.log('Selected side content category:', categoryId);
    loadCategoryDetail(categoryId);
  };

  // 初期表示時に最小IDのカテゴリを自動選択
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
      selectedCategoryId={selectedSideContentCategory ? parseInt(selectedSideContentCategory) : undefined}
    >
        <div className="mb-6">
          {/* パンくずリスト */}
          <div className="flex items-center text-sm text-gray-400 mb-4">
            <a href="/documents" className="hover:text-white">
              <Home className="w-4 h-4 mx-2" />
            </a>
            {categoryDetail && (
              <>
                <span className="mx-2">{'>'}</span>
                <span className="text-white">{categoryDetail.title}</span>
              </>
            )}
          </div>
        </div>

        {/* カテゴリ詳細コンテンツ */}
        <div className="max-w-4xl">
          {loading ? (
            <div className="flex items-center justify-center py-8">
              <div className="text-gray-400">読み込み中...</div>
            </div>
          ) : categoryDetail ? (
            <div className="space-y-6">
              {/* 詳細セクション */}
              <div className="space-y-4">
                <h2 className="text-xl font-semibold text-white">
                  {categoryDetail.title}
                </h2>
                <p className="text-gray-300 leading-relaxed">
                  {categoryDetail.description}
                </p>
              </div>
            </div>
          ) : (
            <div className="text-center py-8">
              <p className="text-gray-400">カテゴリを選択してください</p>
            </div>
          )}
        </div>

      {/* トーストメッセージ */}
      {showToast && (
        <Toast message={toastMessage} type={toastType} onClose={() => setShowToast(false)} />
      )}

    </AdminLayout>
  );
}
