import React, { createContext, useState, useContext, useCallback, useRef, useEffect } from 'react';
import { apiClient } from '@site/src/components/admin/api/client';
import type { UserBranch } from '@site/src/services/branchService';

interface User {
  userId: string;
  email: string;
  lastActivity: string;
}

interface SessionContextType {
  isAuthenticated: boolean;
  user: User | null;
  activeBranch: UserBranch | null;
  checkSession: () => Promise<void>;
  updateActiveBranch: (branch: UserBranch | null) => void;
  isLoading: boolean;
}

export const SessionContext = createContext<SessionContextType>({
  isAuthenticated: false,
  user: null,
  activeBranch: null,
  checkSession: async () => {},
  updateActiveBranch: () => {},
  isLoading: true,
});

export const useSession = () => useContext(SessionContext);

export const SessionProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [user, setUser] = useState<User | null>(null);
  const [activeBranch, setActiveBranch] = useState<UserBranch | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const lastCheckRef = useRef<number>(0);

  // アクティブブランチを更新する関数
  const updateActiveBranch = useCallback((branch: UserBranch | null) => {
    setActiveBranch(branch);
  }, []);

  const checkSession = useCallback(async () => {
    const now = Date.now();
    if (now - lastCheckRef.current < 5000) {
      return;
    }

    setIsLoading(true);

    try {
      const response = await apiClient.get('/auth/session');
      setIsAuthenticated(response.authenticated);
      setUser(response.user || null);
      
      // アクティブブランチの情報も取得
      if (response.authenticated && response.user) {
        try {
          const branchResponse = await apiClient.get('/admin/git/current-branch');
          if (branchResponse.success && branchResponse.branch) {
            setActiveBranch(branchResponse.branch);
          } else {
            setActiveBranch(null);
          }
        } catch (branchError) {
          console.error('アクティブブランチ取得エラー:', branchError);
          setActiveBranch(null);
        }
      } else {
        setActiveBranch(null);
      }
      
      lastCheckRef.current = now;
    } catch (err) {
      console.error('セッション確認エラー:', err);
      setIsAuthenticated(false);
      setUser(null);
      setActiveBranch(null);
      lastCheckRef.current = now;
    } finally {
      setIsLoading(false);
    }
  }, []);

  // アプリケーションの起動時に一度だけセッション確認を行う
  useEffect(() => {
    checkSession();

    // オプション: 定期的なセッション確認
    const interval = setInterval(() => {
      checkSession();
    }, 300000); // 5分ごとに確認

    return () => clearInterval(interval);
  }, [checkSession]);

  return (
    <SessionContext.Provider value={{ isAuthenticated, user, activeBranch, checkSession, updateActiveBranch, isLoading }}>
      {children}
    </SessionContext.Provider>
  );
};
