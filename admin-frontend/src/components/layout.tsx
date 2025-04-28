import React from 'react';
import { Link } from 'react-router-dom';

interface AdminLayoutProps {
  children: React.ReactNode;
  title: string;
}

/**
 * 管理画面のレイアウトコンポーネント
 */
export default function AdminLayout({ children, title }: AdminLayoutProps) {
  return (
    <div className="min-h-screen bg-gray-950 text-white">
      {/* ヘッダー */}
      <header className="bg-gray-900 border-b border-gray-800">
        <div className="container mx-auto px-4 py-4 flex justify-between items-center">
          <Link to="/documents" className="text-xl font-bold">
            ハンドブック管理
          </Link>
          <nav>
            <ul className="flex space-x-4">
              <li>
                <Link to="/documents" className="hover:text-blue-400">
                  ドキュメント
                </Link>
              </li>
              <li>
                <button className="hover:text-blue-400">ログアウト</button>
              </li>
            </ul>
          </nav>
        </div>
      </header>

      {/* メインコンテンツ */}
      <main className="container mx-auto px-4 py-6">
        <h1 className="text-2xl font-bold mb-6">{title}</h1>
        {children}
      </main>

      {/* フッター */}
      <footer className="bg-gray-900 border-t border-gray-800 py-4 mt-auto">
        <div className="container mx-auto px-4 text-center text-gray-400">
          &copy; {new Date().getFullYear()} ハンドブック管理システム
        </div>
      </footer>
    </div>
  );
}
