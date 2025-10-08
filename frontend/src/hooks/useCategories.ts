import useAspidaSWR from '@aspida/swr';
import { client } from '@/api/client';

/**
 * カテゴリ一覧を取得するカスタムフック
 */
export const useCategories = (parentEntityId?: number | null) => {
  const query = parentEntityId !== null && parentEntityId !== undefined 
    ? { parent_entity_id: parentEntityId } 
    : undefined;

  const { data, error, mutate, isLoading } = useAspidaSWR(
    client.category_entities,
    { query }
  );

  return {
    categories: data?.categories || [],
    isLoading,
    isError: error,
    mutate,
  };
};


