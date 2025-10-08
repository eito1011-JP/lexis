import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { client } from '@/api/client';

interface User {
  id: number;
  email: string;
  name?: string;
}

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  checkAuth: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const isAuthenticated = user !== null;

  /**
   * cookieベースの認証状態をチェック
   */
  const checkAuth = async (): Promise<void> => {
    try {
      setIsLoading(true);
      // cookieが自動で送信されるため、認証が必要なエンドポイントを呼び出して確認
      const response = await client.auth.me.$get();
      setUser(response.user);
    } catch (error: any) {
      console.log('認証チェック失敗:', error);
      setUser(null);
      // 401エラーの場合は認証が切れているだけなので、エラーログは不要
      if (error?.response?.status !== 401) {
        console.error('認証チェックエラー:', error);
      }
    } finally {
      setIsLoading(false);
    }
  };

  /**
   * ログイン処理
   */
  const login = async (email: string, password: string): Promise<void> => {
    try {
      await client.auth.signin_with_email.$post({
        body: {
          email,
          password,
        },
      });
      
      // ログイン成功後に認証状態を再チェック
      await checkAuth();
    } catch (error) {
      console.error('ログインエラー:', error);
      throw error;
    }
  };

  /**
   * ログアウト処理
   */
  const logout = async (): Promise<void> => {
    try {
      await client.auth.logout.$post({ body: {} });
    } catch (error) {
      console.error('ログアウトエラー:', error);
      // ログアウトAPIが失敗してもフロントエンド側の状態はクリア
    } finally {
      setUser(null);
      // ページをリロードしてcookieがクリアされた状態にする
      window.location.href = '/login';
    }
  };

  /**
   * 初回マウント時の認証状態チェック
   */
  useEffect(() => {
    checkAuth();
  }, []);

  const value: AuthContextType = {
    user,
    isAuthenticated,
    isLoading,
    login,
    logout,
    checkAuth,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};
