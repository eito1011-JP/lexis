import React, { createContext, useContext, useState, useEffect } from 'react';
import { apiClient } from '@site/src/components/admin/api/client';

interface SessionContextType {
  isAuthenticated: boolean;
  user: {
    id: string;
    email: string;
    createdAt: string;
  } | null;
  checkSession: () => Promise<void>;
}

const SessionContext = createContext<SessionContextType>({
  isAuthenticated: false,
  user: null,
  checkSession: async () => {},
});

export const useSession = () => useContext(SessionContext);

export const SessionProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [user, setUser] = useState<SessionContextType['user']>(null);
  const [lastCheck, setLastCheck] = useState<number>(0);

  const checkSession = async () => {
    const now = Date.now();
    // 前回のチェックから5秒以上経過している場合のみAPIを呼び出す
    if (now - lastCheck < 5000) {
      return;
    }

    try {
      const response = await apiClient.get('/auth/session');
      setIsAuthenticated(response.authenticated);
      setUser(response.user || null);
      setLastCheck(now);
    } catch (err) {
      console.error('セッション確認エラー:', err);
      setIsAuthenticated(false);
      setUser(null);
      setLastCheck(now);
    }
  };

  useEffect(() => {
    checkSession();
  }, []);

  return (
    <SessionContext.Provider value={{ isAuthenticated, user, checkSession }}>
      {children}
    </SessionContext.Provider>
  );
};
