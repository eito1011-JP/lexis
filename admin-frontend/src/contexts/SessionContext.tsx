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

interface SessionContextType {
  user: User | null;
  activeBranch: Branch | null;
  loading: boolean;
  checkSession: () => Promise<void>;
  logout: () => Promise<void>;
}

const SessionContext = createContext<SessionContextType>({
  user: null,
  activeBranch: null,
  loading: true,
  checkSession: async () => {},
  logout: async () => {},
});

export const useSession = () => useContext(SessionContext);

export const SessionProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [activeBranch, setActiveBranch] = useState<Branch | null>(null);
  const [loading, setLoading] = useState(true);

  const checkSession = async () => {
    try {
      setLoading(true);
      const response = await apiClient.get(API_CONFIG.ENDPOINTS.SESSION);

      if (response.data?.user) {
        setUser(response.data.user);
        setActiveBranch(response.data.activeBranch || null);
      } else {
        setUser(null);
        setActiveBranch(null);
      }
    } catch (error) {
      setUser(null);
      setActiveBranch(null);
    } finally {
      setLoading(false);
    }
  };

  const logout = async () => {
    try {
      await apiClient.post(API_CONFIG.ENDPOINTS.LOGOUT, {});
      setUser(null);
      setActiveBranch(null);
      window.location.href = '/admin/login';
    } catch (error) {
      console.error('Logout error:', error);
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
