import { useEffect } from 'react';
import { useHistory } from '@docusaurus/router';
import { apiClient } from '@site/src/components/admin/api/client';

export const useSessionCheck = (
  redirectPath: string = '/admin/signup',
  shouldRedirectIfAuthenticated: boolean = true
) => {
  const history = useHistory();

  useEffect(() => {
    const checkSession = async () => {
      try {
        const response = await apiClient.get('/auth/session');
        if (shouldRedirectIfAuthenticated && response.authenticated) {
          history.push(redirectPath);
        } else if (!shouldRedirectIfAuthenticated && !response.authenticated) {
          history.push(redirectPath);
        }
      } catch (err) {
        console.error('セッション確認エラー:', err);
        if (!shouldRedirectIfAuthenticated) {
          history.push(redirectPath);
        }
      }
    };

    checkSession();
  }, [history, redirectPath, shouldRedirectIfAuthenticated]);
};
