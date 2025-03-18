import React from 'react';
import { useHistory } from '@docusaurus/router';

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

  const navigateToHome = () => {
    history.push('/');
  };

  return (
      <header className="sticky top-0 z-10 bg-gray-900 dark:bg-gray-800 text-white shadow-md">
        <div className="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center">
          <div className="text-xl font-semibold">管理画面</div>
          <div className="flex gap-2">
            <button 
              onClick={navigateToHome}
              className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm transition-colors"
            >
              ユーザー画面へ
            </button>
          </div>
        </div>
      </header>
  );
}
