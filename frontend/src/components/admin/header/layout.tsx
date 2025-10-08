import React from 'react';
import { useAuth } from '@/contexts/AuthContext';
import { useUserMe } from '@/hooks/useUserMe';

const PATHS = {
  ADMIN_LOGIN: '/login',
} as const;

const STYLES = {
  header: 'sticky top-0 z-10 bg-[#0A0A0A] dark:bg-gray-800 text-white shadow-md',
  container: 'max-w-7xl mx-auto px-4 h-16 flex justify-between items-center',
  logo: 'text-3xl font-bold',
  navContainer: 'flex items-center gap-4',
  button:
    'font-bold text-center border border-[#DEDEDE] bg-transparent text-white py-2 rounded text-sm transition-colors w-32',
} as const;

/**
 * ユーザー情報表示コンポーネント
 * useUserMeフックを使用して、ユーザー、組織、アクティブブランチ情報を表示
 */
const UserInfo: React.FC = () => {
  const { user, isLoading } = useUserMe();

  if (isLoading) {
    return <span className="text-gray-400">読み込み中...</span>;
  }

  if (!user) {
    return null;
  }

  return (
    <div className="flex items-center gap-3">
      <div className="flex flex-col items-end text-sm">
        <span className="font-medium">{user.email}でログイン中</span>
      </div>
    </div>
  );
};


/**
 * 管理画面用のヘッダーコンポーネント
 */
function Header(): React.ReactElement {
  const { isAuthenticated } = useAuth();

  return (
    <header className={STYLES.header}>
      <div className={STYLES.container}>
        <div className={STYLES.logo}>Lexis</div>
        <div className={STYLES.navContainer}>
          {isAuthenticated && <UserInfo />}
        </div>
      </div>
    </header>
  );
}

export default Header;
