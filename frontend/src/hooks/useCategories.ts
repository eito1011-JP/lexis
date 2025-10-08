import useSWR from 'swr';
import { client } from '@/api/client';
import { useUserMe } from './useUserMe';

/**
 * カテゴリ一覧を取得するカスタムフック
 */
export const useCategories = (parentEntityId?: number | null) => {
  const { activeUserBranch } = useUserMe();
  
  // キャッシュキーにactiveUserBranchのIDを含めることで、
  // ブランチが変更されたときに自動的に新しいデータを取得
  const { data, error, mutate, isLoading } = useSWR(
    ['categories', parentEntityId, activeUserBranch?.id || 'main'],
    async () => {
      const query = parentEntityId !== null && parentEntityId !== undefined 
        ? { parent_entity_id: parentEntityId } 
        : undefined;
      
      const response = await client.category_entities.$get({ query });
      return response;
    }
  );

  return {
    categories: data?.categories || [],
    isLoading,
    isError: error,
    mutate,
  };
};


