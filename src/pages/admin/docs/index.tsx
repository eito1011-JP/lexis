import AdminLayout from '@site/src/components/admin/layout';
import React from 'react';

/**
 * 管理画面のドキュメント一覧ページコンポーネント
 */
export default function AdminPage(): JSX.Element {
  // 新規エントリー追加ハンドラー
  const handleAddEntry = () => {
    console.log('新規エントリーを追加します');
    // 実装: 新規ドキュメント作成ロジック
  };

  return (
    <AdminLayout title="ドキュメント管理">
      <div className="flex flex-col h-full">
        <div className="mb-6">
          <h1 className="text-2xl font-bold mb-4">ドキュメント</h1>
          
          {/* 検索とアクションエリア */}
          <div className="flex items-center justify-between mb-6">
            <button
              className="ml-auto bg-white text-black rounded-md px-4 py-2 font-medium"
              onClick={handleAddEntry}
            >
              新規ドキュメントを作成
            </button>
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
              <svg className="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
              </svg>
              <span>tutorial-basics</span>
            </div>
            <div className="flex items-center p-3 bg-gray-900 rounded-md border border-gray-800">
              <svg className="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
              </svg>
              <span>tutorial-extras</span>
            </div>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
