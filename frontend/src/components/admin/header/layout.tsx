import React from 'react';
import { Link } from 'react-router-dom';
import { API_CONFIG } from '../api/config';
import { useAuth } from '@/contexts/AuthContext';

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

const UserInfo: React.FC<{ email: string }> = ({ email }) => <span>{email}でログイン中</span>;


/**
 * 管理画面用のヘッダーコンポーネント
 */
function Header(): React.ReactElement {
  const { user, isAuthenticated } = useAuth();

  return (
    <header className={STYLES.header}>
      <div className={STYLES.container}>
        <div className={STYLES.logo}>Lexis</div>
        <div className={STYLES.navContainer}>
          {isAuthenticated && user ? (
            <>
              <UserInfo email={user.email} />
            </>
          ) : (
            <></>
          )}
        </div>
      </div>
    </header>
  );
}

export default Header;
