import { useEffect } from 'react';
import { useSession } from '../contexts/SessionContext';

/**
 * セッションをチェックし、認証状態に応じてリダイレクトするフック
 *
 * @param redirectPath - リダイレクト先のパス
 * @param redirectIfAuthenticated - true の場合、認証済みならリダイレクト。false の場合、未認証ならリダイレクト
 */
export const useSessionCheck = (redirectPath: string, redirectIfAuthenticated: boolean = false) => {
  const { user, loading } = useSession();

  useEffect(() => {
    if (!loading) {
      const isAuthenticated = !!user;

      if (
        (redirectIfAuthenticated && isAuthenticated) ||
        (!redirectIfAuthenticated && !isAuthenticated)
      ) {
        window.location.href = redirectPath;
      }
    }
  }, [user, loading, redirectPath, redirectIfAuthenticated]);

  return { loading };
};
