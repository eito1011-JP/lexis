import React, { createContext, useContext, useState, useEffect } from 'react';
import { apiClient } from '../components/admin/api/client';
import { API_CONFIG } from '../components/admin/api/config';

interface User {
  id: string;
  email: string;
  role: string;
}

interface Branch {
  branchId: string;
  branchName: string;
}

interface SessionResponse {
  authenticated: boolean;
  user?: {
    userId?: string;
    id?: string;
    email?: string;
    role?: string;
  };
  activeBranch?: Branch | null;
}

interface SessionContextType {
  user: User | null;
  activeBranch: Branch | null;
  loading: boolean;
  checkSession: () => Promise<void>;
  logout: () => Promise<void>;
}

const DEFAULT_CONTEXT: SessionContextType = {
  user: null,
  activeBranch: null,
  loading: true,
  checkSession: async () => {},
  logout: async () => {},
};

const SessionContext = createContext<SessionContextType>(DEFAULT_CONTEXT);

export const useSession = () => useContext(SessionContext);

const resetSession = (
  setUser: (user: User | null) => void,
  setActiveBranch: (branch: Branch | null) => void
) => {
  setUser(null);
  setActiveBranch(null);
};

export const SessionProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [activeBranch, setActiveBranch] = useState<Branch | null>(null);
  const [loading, setLoading] = useState(true);

  const checkSession = async () => {
    try {
      setLoading(true);
      const response = (await apiClient.get(API_CONFIG.ENDPOINTS.SESSION)) as SessionResponse;

      if (!response.authenticated) {
        resetSession(setUser, setActiveBranch);
        return;
      }

      const userData = response.user || {};
      const newUser: User = {
        id: userData.userId || userData.id || '',
        email: userData.email || '',
        role: userData.role || 'user',
      };

      setUser(newUser);
      setActiveBranch(response.activeBranch || null);
    } catch (error) {
      console.error('セッションチェックエラー:', error);
      resetSession(setUser, setActiveBranch);
    } finally {
      setLoading(false);
    }
  };

  const logout = async () => {
    try {
      await apiClient.post(API_CONFIG.ENDPOINTS.LOGOUT, {});
      resetSession(setUser, setActiveBranch);
      window.location.href = '/login';
    } catch (error) {
      console.error('ログアウトエラー:', error);
      throw error;
    }
  };

  useEffect(() => {
    checkSession();
  }, []);

  return (
    <SessionContext.Provider
      value={{
        user,
        activeBranch,
        loading,
        checkSession,
        logout,
      }}
    >
      {children}
    </SessionContext.Provider>
  );
};
