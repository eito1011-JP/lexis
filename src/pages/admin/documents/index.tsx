import AdminLayout from '@site/src/components/admin/layout';
import React, { useState } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@site/src/hooks/useSessionCheck';

/**
 * 管理画面のドキュメント一覧ページコンポーネント
 */
export default function DocumentsPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/admin/login', false);

  const [showFolderModal, setShowFolderModal] = useState(false);
  const [folderName, setFolderName] = useState('');

  const handleCreateImageFolder = () => {
    setShowFolderModal(true);
  };

  const handleCloseModal = () => {
    setShowFolderModal(false);
    setFolderName('');
  };

  const handleCreateFolder = () => {
    console.log('フォルダを作成:', folderName);

    handleCloseModal();
  };

  // セッション確認中はローディング表示
  if (isLoading) {
    return (
      <AdminLayout title="読み込み中...">
        <div className="flex flex-col items-center justify-center h-full">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout title="ドキュメント管理">
      <div className="flex flex-col h-full">
        <div className="mb-6">
          <h1 className="text-2xl font-bold mb-4">ドキュメント</h1>
          {/* 検索とアクションエリア */}
          <div className="flex items-center justify-between mb-6">
            <div className="flex items-center gap-4 ml-auto">
              <button
                className="bg-gray-900 rounded-xl w-12 h-12 flex items-center justify-center border border-gray-700"
                onClick={handleCreateImageFolder}
                title="フォルダ作成"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  className="h-6 w-6 text-white"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                  />
                </svg>
              </button>
              <button
                className="flex items-center px-4 py-2 bg-white text-[#0A0A0A] rounded-md hover:bg-gray-100"
                onClick={() => {
                  window.location.href = '/admin/documents/new';
                }}
              >
                新規ドキュメント作成
              </button>
            </div>
          </div>

          {/* テーブルヘッダー */}
          <div className="grid grid-cols-12 border-b border-gray-700 pb-2 text-sm text-gray-400">
            <div className="col-span-4 flex items-center">
              <span>タイトル</span>
            </div>
            <div className="col-span-3">コンテンツ</div>
            <div className="col-span-3">公開ステータス</div>
            <div className="col-span-2">最終編集者</div>
          </div>
        </div>

        {/* フォルダーセクション */}
        <div className="mt-8">
          <h2 className="text-xl font-bold mb-4">フォルダー</h2>
          <div className="grid grid-cols-2 gap-4">
            <div className="flex items-center p-3 bg-gray-900 rounded-md border border-gray-800">
              <svg
                className="w-5 h-5 mr-2 text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                ></path>
              </svg>
              <span>tutorial-basics</span>
            </div>
            <div className="flex items-center p-3 bg-gray-900 rounded-md border border-gray-800">
              <svg
                className="w-5 h-5 mr-2 text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                ></path>
              </svg>
              <span>tutorial-extras</span>
            </div>
          </div>
        </div>

        {/* フォルダ作成モーダル */}
        {showFolderModal && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-gray-900 rounded-lg p-6 w-full max-w-md border border-gray-800">
              <div className="flex justify-between items-center mb-4">
                <h3 className="text-xl font-bold">フォルダを作成</h3>
                <button onClick={handleCloseModal} className="text-gray-400 hover:text-white">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    className="h-6 w-6"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M6 18L18 6M6 6l12 12"
                    />
                  </svg>
                </button>
              </div>
              <p className="text-gray-400 mb-4">フォルダー名を入力してください</p>
              <input
                type="text"
                value={folderName}
                onChange={e => setFolderName(e.target.value)}
                className="w-full p-2 mb-4 bg-black border border-gray-700 rounded text-white"
                placeholder="フォルダ名を入力"
                autoFocus
              />
              <div className="flex justify-end gap-3">
                <button
                  onClick={handleCloseModal}
                  className="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-600"
                >
                  キャンセル
                </button>
                <button
                  onClick={handleCreateFolder}
                  className="px-4 py-2 bg-white text-black rounded hover:bg-gray-200"
                  disabled={!folderName.trim()}
                >
                  作成
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
