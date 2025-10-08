import useSWR from 'swr';
import { client } from '@/api/client';

/**
 * ドキュメント詳細を取得するカスタムフック
 */
export const useDocumentDetail = (documentEntityId?: number | null) => {
  const { data, error, mutate, isLoading } = useSWR(
    documentEntityId ? ['document-detail', documentEntityId] : null,
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


