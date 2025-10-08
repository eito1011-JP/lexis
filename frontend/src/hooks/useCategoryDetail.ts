import useSWR from 'swr';
import { client } from '@/api/client';
import { useUserMe } from './useUserMe';

/**
 * カテゴリ詳細を取得するカスタムフック
 */
export const useCategoryDetail = (categoryEntityId?: number | null) => {
  const { activeUserBranch } = useUserMe();
  
  // キャッシュキーにactiveUserBranchのIDを含めることで、
  // ブランチが変更されたときに自動的に新しいデータを取得
  const { data, error, mutate, isLoading } = useSWR(
    categoryEntityId ? ['category-detail', categoryEntityId, activeUserBranch?.id || 'main'] : null,
    async () => {
      if (!categoryEntityId) return null;
      const response = await client.category_entities._entityId(categoryEntityId).$get();
      return response.category;
    }
  );

  return {
    categoryDetail: data,
    isLoading,
    isError: error,
    mutate,
  };
};


