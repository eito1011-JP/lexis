import useAspidaSWR from '@aspida/swr';
import { client } from '@/api/client';

/**
 * ユーザーブランチの変更をチェックするカスタムフック
 */
export const useUserBranchChanges = () => {
  const { data, error, mutate, isLoading } = useAspidaSWR(
    client.user_branches.has_changes,
    {}
  );

  return {
    hasUserChanges: data?.has_user_changes || false,
    userBranchId: data?.user_branch_id,
    isLoading,
    isError: error,
    mutate,
  };
};


