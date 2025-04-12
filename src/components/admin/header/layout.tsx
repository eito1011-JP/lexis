import React from 'react';
import { useHistory } from '@docusaurus/router';
import { useSession } from '@site/src/contexts/SessionContext';

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
  const history = useHistory();
  const { isAuthenticated, user } = useSession();

  const navigateTo = (path: string) => {
    history.push(path);
  };

  return (
    <>
      {isAuthenticated ? (
        <AuthenticatedHeader email={user?.email} navigateTo={navigateTo} />
      ) : (
        <UnauthenticatedHeader navigateTo={navigateTo} />
      )}
    </>
  );
}

/**
 * 認証済みユーザー向けヘッダーコンポーネント
 */
function AuthenticatedHeader({ 
  email, 
  navigateTo 
}: { 
  email?: string; 
  navigateTo: (path: string) => void 
}): React.ReactElement {
  return (
    <header className="sticky top-0 z-10 bg-[#0A0A0A] dark:bg-gray-800 text-white shadow-md">
      <div className="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center">
        <div className="text-3xl font-bold">Lexis</div>
        <div className="flex items-center gap-4">
          <span>{email}でログイン中</span>
          <button
            onClick={() => navigateTo(PATHS.HOME)}
            className="font-bold border border-[#DEDEDE] bg-transparent hover:bg-gray-100 text-white py-2 rounded text-sm transition-colors w-32"
          >
            ユーザー画面へ
          </button>
        </div>
      </div>
    </header>
  );
}

/**
 * 未認証ユーザー向けヘッダーコンポーネント
 */
function UnauthenticatedHeader({ 
  navigateTo 
}: { 
  navigateTo: (path: string) => void 
}): React.ReactElement {
  return (
    <header className="sticky top-0 z-10 bg-[#0A0A0A] dark:bg-gray-800 text-white shadow-md">
      <div className="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center">
        <div className="text-3xl font-bold">Lexis</div>
        <div className="flex gap-2 rounded-lg">
          <button
            onClick={() => navigateTo(PATHS.HOME)}
            className="font-bold border border-[#DEDEDE] bg-transparent hover:bg-gray-100 text-white py-2 rounded text-sm transition-colors w-32"
          >
            ユーザー画面へ
          </button>
          <button
            onClick={() => navigateTo(PATHS.ADMIN_LOGIN)}
            className="font-bold border border-[#DEDEDE] bg-transparent hover:bg-gray-100 text-white py-2 rounded text-sm transition-colors w-32"
          >
            ログイン
          </button>
        </div>
      </div>
    </header>
  );
}
