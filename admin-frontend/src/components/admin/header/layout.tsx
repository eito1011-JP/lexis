import React from 'react';
import { Link } from 'react-router-dom';
import { useSession } from '../../../contexts/SessionContext';

const PATHS = {
  HOME: '/',
  ADMIN_LOGIN: '/admin/login',
} as const;

/**
 * 管理画面用のページコンポーネント
 */
export default function AdminPage(): React.ReactElement {
  return (
    <>
      <AdminContent />
    </>
  );
}

/**
 * 管理画面用のコンテンツコンポーネント
 */
function AdminContent(): React.ReactElement {
  const { isAuthenticated, user } = useSession();

  return (
    <>{isAuthenticated ? <AuthenticatedHeader email={user?.email} /> : <UnauthenticatedHeader />}</>
  );
}

/**
 * 認証済みユーザー向けヘッダーコンポーネント
 */
function AuthenticatedHeader({ email }: { email?: string }): React.ReactElement {
  return (
    <header className="sticky top-0 z-10 bg-[#0A0A0A] dark:bg-gray-800 text-white shadow-md">
      <div className="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center">
        <div className="text-3xl font-bold">Lexis</div>
        <div className="flex items-center gap-4">
          <span>{email}でログイン中</span>
          <Link
            to={PATHS.HOME}
            className="font-bold border text-center border-[#DEDEDE] bg-transparent text-white py-2 rounded text-sm transition-colors w-32"
          >
            ユーザー画面へ
          </Link>
        </div>
      </div>
    </header>
  );
}

/**
 * 未認証ユーザー向けヘッダーコンポーネント
 */
function UnauthenticatedHeader(): React.ReactElement {
  return (
    <header className="sticky top-0 z-10 bg-[#0A0A0A] dark:bg-gray-800 text-white shadow-md">
      <div className="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center">
        <div className="text-3xl font-bold">Lexis</div>
        <div className="flex gap-2 rounded-lg">
          <Link
            to={PATHS.HOME}
            className="font-bold text-center border border-[#DEDEDE] bg-transparent text-white py-2 rounded text-sm transition-colors w-32"
          >
            ユーザー画面へ
          </Link>
          <Link
            to={PATHS.ADMIN_LOGIN}
            className="font-bold text-center border border-[#DEDEDE] bg-transparent text-white py-2 rounded text-sm transition-colors w-32"
          >
            ログイン
          </Link>
        </div>
      </div>
    </header>
  );
}
