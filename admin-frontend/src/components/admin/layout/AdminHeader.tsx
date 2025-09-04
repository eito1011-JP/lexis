import React from 'react';

export default function AdminHeader(): React.JSX.Element {
  const token = typeof window !== 'undefined' ? localStorage.getItem('access_token') : null;
  const email = typeof window !== 'undefined' ? localStorage.getItem('user_email') || '' : '';
  const isAuthenticated = !!token;
  const activeBranch = null;

  return (
    <header className="border-b border-gray-800 py-4 px-6 flex justify-between items-center">
      <div className="flex items-center gap-6">
        {/* ブランチ情報表示 */}
        {activeBranch && (
          <div className="flex items-center px-3 py-1 bg-gray-800 rounded-full text-sm">
            <svg className="w-4 h-4 mr-1 text-green-400" viewBox="0 0 16 16" fill="currentColor">
              <path d="M11.75 2.5a0.75 0.75 0 100 1.5 0.75 0.75 0 000-1.5zm-2.25 0.75a2.25 2.25 0 113 2.122V6A2.5 2.5 0 0110 8.5H6a1 1 0 00-1 1v1.128a2.251 2.251 0 11-1.5 0V5.372a2.25 2.25 0 111.5 0v1.836A2.492 2.492 0 016 7h4a1 1 0 001-1v-1.628a2.25 2.25 0 01-1.5-2.122zM2.5 10.75a0.75 0.75 0 100 1.5 0.75 0.75 0 000-1.5zM2.5 3.75a0.75 0.75 0 100 1.5 0.75 0.75 0 000-1.5z"></path>
            </svg>
            <span className="text-white">{activeBranch.branchName}</span>
          </div>
        )}

        {/* ユーザー情報 */}
        <div className="flex items-center gap-2">
          <div className="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-white">
            {isAuthenticated && email ? email.charAt(0).toUpperCase() : '?'}
          </div>
          <div className="text-sm text-gray-300">{isAuthenticated && email ? email : '未ログイン'}</div>
        </div>
      </div>
    </header>
  );
}
