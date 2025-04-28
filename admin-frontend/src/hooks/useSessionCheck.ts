import { useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';

export const useSessionCheck = (redirectPath: string, redirectIfAuth: boolean) => {
  const [isAuthenticated, setIsAuthenticated] = useState<boolean | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const navigate = useNavigate();

  useEffect(() => {
    const checkSession = async () => {
      try {
        const response = await apiClient.get(API_CONFIG.ENDPOINTS.SESSION);

        setIsAuthenticated(response.authenticated);

        if (redirectIfAuth && response.authenticated) {
          navigate(redirectPath);
        } else if (!redirectIfAuth && !response.authenticated) {
          navigate(redirectPath);
        }
      } catch (error) {
        console.error('Session check error:', error);
        setIsAuthenticated(false);
        navigate(redirectPath);
      } finally {
        setIsLoading(false);
      }
    };

    checkSession();
  }, [redirectPath, redirectIfAuth, navigate]);

  return { isAuthenticated, isLoading };
};
