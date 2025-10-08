import useSWR from 'swr';
import { client } from '@/api/client';

/**
 * カテゴリ詳細を取得するカスタムフック
 */
export const useCategoryDetail = (categoryEntityId?: number | null) => {
  const { data, error, mutate, isLoading } = useSWR(
    categoryEntityId ? ['category-detail', categoryEntityId] : null,
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


