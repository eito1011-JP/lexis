import useSWR from 'swr';
import { client } from '@/api/client';
import { useUserMe } from './useUserMe';

/**
 * ドキュメント詳細を取得するカスタムフック
 */
export const useDocumentDetail = (documentEntityId?: number | null) => {
  const { activeUserBranch } = useUserMe();
  
  // キャッシュキーにactiveUserBranchのIDを含めることで、
  // ブランチが変更されたときに自動的に新しいデータを取得
  const { data, error, mutate, isLoading } = useSWR(
    documentEntityId ? ['document-detail', documentEntityId, activeUserBranch?.id || 'main'] : null,
    async () => {
      if (!documentEntityId) return null;
      const response = await client.document_entities._entityId(documentEntityId).$get();
      return response;
    }
  );

  return {
    documentDetail: data,
    isLoading,
    isError: error,
    mutate,
  };
};


