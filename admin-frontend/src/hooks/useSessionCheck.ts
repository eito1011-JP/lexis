import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiClient } from '../api/client';

/**
 * セッションのチェックを行うカスタムフック
 * @param redirectPath - 認証されていない場合のリダイレクト先
 * @param requireAuth - 認証が必要かどうか
 */
export const useSessionCheck = (redirectPath = '/login', requireAuth = true) => {
  const [isAuthenticated, setIsAuthenticated] = useState<boolean | null>(null);
  const [user, setUser] = useState<any>(null);
  const [isLoading, setIsLoading] = useState(true);
  const navigate = useNavigate();

  useEffect(() => {
    const checkSession = async () => {
      try {
        const response = await apiClient.get('/admin/check-session');
        setIsAuthenticated(true);
        setUser(response.user);
      } catch (error) {
        setIsAuthenticated(false);
        setUser(null);
        if (requireAuth) {
          navigate(redirectPath);
        }
      } finally {
        setIsLoading(false);
      }
    };

    checkSession();
  }, [navigate, redirectPath, requireAuth]);

  return { isAuthenticated, user, isLoading };
}; 