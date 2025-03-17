import React from 'react';
import { useHistory } from '@docusaurus/router';
import Layout from '@theme/Layout';
import {useColorMode} from '@docusaurus/theme-common';
import ExecutionEnvironment from '@docusaurus/ExecutionEnvironment';

// クライアントサイドでのみスタイルを適用するためのコード
if (ExecutionEnvironment.canUseDOM) {
  // ナビゲーションバーを非表示にするスタイルを動的に追加
  const style = document.createElement('style');
  style.innerHTML = `
    .admin-page .navbar { display: none !important; }
    .admin-page .main-wrapper { margin-top: 0 !important; padding-top: 0 !important; }
  `;
  document.head.appendChild(style);
}

/**
 * 管理画面用のページコンポーネント
 */
export default function AdminPage(): JSX.Element {
  return (
    <Layout
      title="管理画面"
      wrapperClassName="admin-page"
    >
      <AdminContent />
    </Layout>
  );
}

/**
 * 管理画面用のコンテンツコンポーネント
 */
function AdminContent(): JSX.Element {
  const history = useHistory();
  const {colorMode, setColorMode} = useColorMode();
  const isDarkMode = colorMode === 'dark';

  const navigateToHome = () => {
    history.push('/');
  };

  const toggleColorMode = () => {
    setColorMode(isDarkMode ? 'light' : 'dark');
  };

  return (
    <div className="min-h-screen">
      {/* カスタムヘッダー */}
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
            <button 
              onClick={toggleColorMode}
              className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm transition-colors"
            >
              {isDarkMode ? '🌞 ライトモード' : '🌙 ダークモード'}
            </button>
          </div>
        </div>
      </header>

      {/* メインコンテンツ */}
      <main className="max-w-7xl mx-auto px-4 py-6">
        <h1 className="text-2xl font-bold text-blue-500 mb-6">ドキュメント</h1>
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
          {/* コンテンツ内容 */}
        </div>
      </main>
    </div>
  );
}
