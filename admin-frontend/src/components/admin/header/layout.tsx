import React from 'react';
import { Link } from 'react-router-dom';
import { API_CONFIG } from '../api/config';

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

const NavigationButtons: React.FC<{ isAuthenticated: boolean }> = ({ isAuthenticated }) => (
  <div className="flex gap-2 rounded-lg">
    <Link
      to={`${API_CONFIG.FRONTEND_URLS.USER_FRONTEND}/docs/category/tutorial---basics`}
      className={STYLES.button}
    >
      ユーザー画面へ
    </Link>
    {!isAuthenticated && (
      <Link to={PATHS.ADMIN_LOGIN} className={STYLES.button}>
        ログイン
      </Link>
    )}
  </div>
);

/**
 * 管理画面用のヘッダーコンポーネント
 */
function Header(): React.ReactElement {
  const token = typeof window !== 'undefined' ? localStorage.getItem('access_token') : null;
  const email = typeof window !== 'undefined' ? localStorage.getItem('user_email') || '' : '';
  const isAuthenticated = !!token;

  return (
    <header className={STYLES.header}>
      <div className={STYLES.container}>
        <div className={STYLES.logo}>Lexis</div>
        <div className={STYLES.navContainer}>
          {isAuthenticated ? (
            <>
              <UserInfo email={email} />
              <NavigationButtons isAuthenticated={isAuthenticated} />
            </>
          ) : (
            <NavigationButtons isAuthenticated={isAuthenticated} />
          )}
        </div>
      </div>
    </header>
  );
}

export default Header;
