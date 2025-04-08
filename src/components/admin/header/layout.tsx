import React from 'react';
import { useHistory } from '@docusaurus/router';
import { useSessionCheck } from '@site/src/hooks/useSessionCheck';

const PATHS = {
  HOME: '/',
  ADMIN_LOGIN: '/admin/login',
} as const;

/**
 * 管理画面用のページコンポーネント
 */
export default function AdminPage(): JSX.Element {
  return (
    <>
      <AdminContent />
    </>
  );
}

/**
 * 管理画面用のコンテンツコンポーネント
 */
function AdminContent(): JSX.Element {
  const history = useHistory();
  const { isAuthenticated } = useSessionCheck();

  const navigateTo = (path: string) => {
    history.push(path);
  };

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
          {!isAuthenticated && (
            <button
              onClick={() => navigateTo(PATHS.ADMIN_LOGIN)}
              className="font-bold border border-[#DEDEDE] bg-transparent hover:bg-gray-100 text-white py-2 rounded text-sm transition-colors w-32"
            >
              ログイン
            </button>
          )}
        </div>
      </div>
    </header>
  );
}
