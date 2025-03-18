import React from 'react';
import Head from '@docusaurus/Head';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Header from './header/layout';
import { useHistory } from '@docusaurus/router';

/**
 * 管理画面用のレイアウトコンポーネント
 */
interface AdminLayoutProps {
  children: React.ReactNode;
  title: string;
}

export default function AdminLayout({ children, title }: AdminLayoutProps): JSX.Element {
  const {siteConfig} = useDocusaurusContext();
  const history = useHistory();
  
  // 現在のパスを取得してアクティブなナビゲーションアイテムを判定
  const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
  
  // サイドバーナビゲーションアイテム
  const navItems = [
    { 
      label: 'ドキュメント', 
      path: '/admin/docs', 
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
      )
    },
    { 
      label: 'メディア', 
      path: '/admin/media', 
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
        </svg>
      )
    },
    { 
      label: '設定', 
      path: '/admin/settings', 
      icon: (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
      )
    }
  ];

  const navigateTo = (path) => {
    history.push(path);
  };

  return (
    <>
      <Head>
        <title>{title} | {siteConfig.title}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      </Head>
      <div className="min-h-screen flex flex-col">
        <Header />
        <div className="flex flex-1">
          {/* サイドバー */}
          <div className="w-60 min-h-screen bg-gray-900 border-r border-gray-800 flex flex-col">
            <nav className="flex-1 py-4">
              {navItems.map((item) => (
                <div 
                  key={item.path}
                  className={`flex items-center px-4 py-3 cursor-pointer ${currentPath === item.path ? 'bg-gray-800 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white'}`}
                  onClick={() => navigateTo(item.path)}
                >
                  {item.icon}
                  <span className="ml-3">{item.label}</span>
                </div>
              ))}
            </nav>
          </div>
          
          {/* メインコンテンツ */}
          <main className="flex-1 bg-black">
            <div className="container mx-auto px-6 py-6">
              {children}
            </div>
          </main>
        </div>
      </div>
    </>
  );
}
