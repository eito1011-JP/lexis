import { useEffect } from 'react';
import { useHistory } from '@docusaurus/router';
import { useSession } from '@site/src/contexts/SessionContext';

export const useSessionCheck = (
  redirectPath: string = '/admin/signup',
  shouldRedirectIfAuthenticated: boolean = true
) => {
  const history = useHistory();
  const { isAuthenticated, isLoading } = useSession();

  useEffect(() => {
    // セッション確認が完了するまでリダイレクトしない
    if (isLoading) {
      return;
    }

    if (shouldRedirectIfAuthenticated && isAuthenticated) {
      history.push(redirectPath);
    } else if (!shouldRedirectIfAuthenticated && !isAuthenticated) {
      history.push(redirectPath);
    }
  }, [isAuthenticated, isLoading, history, redirectPath, shouldRedirectIfAuthenticated]);

  return { isAuthenticated, isLoading };
};
