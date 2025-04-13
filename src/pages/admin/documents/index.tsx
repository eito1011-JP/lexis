import AdminLayout from '@site/src/components/admin/layout';
import React, { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@site/src/hooks/useSessionCheck';
import { apiClient } from '@site/src/components/admin/api/client';

/**
 * 管理画面のドキュメント一覧ページコンポーネント
 */
export default function DocumentsPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/admin/login', false);

  const [showFolderModal, setShowFolderModal] = useState(false);
  const [folderName, setFolderName] = useState('');
  const [isCreating, setIsCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [folders, setFolders] = useState<string[]>([]);
  const [foldersLoading, setFoldersLoading] = useState(true);
  const [showBranchModal, setShowBranchModal] = useState(false);
  const [isBranchCreating, setIsBranchCreating] = useState(false);
  const [branchError, setBranchError] = useState<string | null>(null);
  const [currentUserEmail, setCurrentUserEmail] = useState<string | null>(null);
  const [apiError, setApiError] = useState<string | null>(null);

  useEffect(() => {
    // フォルダー一覧を取得
    const fetchFolders = async () => {
      try {
        const response = await apiClient.get('/admin/documents/folders');
        console.log('フォルダー取得レスポンス:', response);
        if (response.folders) {
          setFolders(response.folders);
        }
      } catch (err) {
        console.error('フォルダー取得エラー:', err);
        setApiError('フォルダーの取得に失敗しました');
      } finally {
        setFoldersLoading(false);
      }
    };

    fetchFolders();

    // ユーザー情報の取得
    const fetchUserInfo = async () => {
      try {
        const response = await apiClient.get('/admin/user/current');
        if (response.email) {
          setCurrentUserEmail(response.email);
        } else {
          // レスポンスにemailが含まれていない場合
          setCurrentUserEmail('unknown');
        }
      } catch (err) {
        console.error('ユーザー情報取得エラー:', err);
        // エラー時はフォールバック値を設定
        setCurrentUserEmail('unknown');
      }
    };

    fetchUserInfo();
  }, []);

  const handleCreateImageFolder = () => {
    setShowFolderModal(true);
  };

  const handleCloseModal = () => {
    setShowFolderModal(false);
    setFolderName('');
  };

  const handleCloseBranchModal = () => {
    setShowBranchModal(false);
    setBranchError(null);
  };

  const handleCreateFolder = async () => {
    if (!folderName.trim()) return;

    setIsCreating(true);
    setError(null);

    try {
      await apiClient.post('/admin/documents/create-folder', { folderName });

      // フォルダーリストを更新
      setFolders(prev => [...prev, folderName]);
      handleCloseModal();
    } catch (err) {
      console.error('フォルダ作成エラー:', err);
      setError(err instanceof Error ? err.message : '不明なエラーが発生しました');
    } finally {
      setIsCreating(false);
    }
  };

  // ブランチの作成とページ遷移を行う関数
  const handleCreateBranch = async () => {
    setIsBranchCreating(true);
    setBranchError(null);

    try {
      // タイムスタンプの作成
      const timestamp = Math.floor(Date.now() / 1000);
      const email = currentUserEmail || 'unknown';
      const branchName = `feature/${email}_${timestamp}`;

      try {
        const response = await apiClient.post('/admin/git/create-branch', {
          branchName,
          fromBranch: 'main',
        });

        if (response && response.success) {
          // 成功したら新規ドキュメント作成ページへ遷移
          window.location.href = '/admin/documents/new';
        } else {
          setBranchError('ブランチの作成に失敗しました');
          console.warn('ブランチ作成：APIが成功を返しませんでした。開発モードでは続行します');
          // 開発モードでは続行
          if (process.env.NODE_ENV === 'development') {
            window.location.href = '/admin/documents/new';
          }
        }
      } catch (err) {
        console.error('ブランチ作成エラー:', err);
        setBranchError(err instanceof Error ? err.message : '不明なエラーが発生しました');
        // 開発モードでは続行
        if (process.env.NODE_ENV === 'development') {
          console.warn('開発モードのため、エラーにもかかわらず続行します');
          window.location.href = '/admin/documents/new';
        }
      }
    } finally {
      setIsBranchCreating(false);
    }
  };

  // 新規ドキュメント作成ボタンのクリックハンドラ
  const handleNewDocumentClick = async () => {
    try {
      // 現在のブランチの変更状態を確認
      try {
        const response = await apiClient.get('/admin/git/check-diff');

        console.log('response', response);
        if (response && response.hasDiff) {
          // 変更がある場合は直接遷移
          window.location.href = '/admin/documents/new';
        } else {
          // 変更がない場合はモーダルを表示
          setShowBranchModal(true);
        }
      } catch (err) {
        console.error('Git状態確認エラー:', err);
        // エラー時はモーダルを表示
        setShowBranchModal(true);
      }
    } catch (err) {
      console.error('予期しないエラー:', err);
      setApiError('予期しないエラーが発生しました');
      // ダミーデータを使用して続行
      setShowBranchModal(true);
    }
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

  // フォルダーセクション
  const renderFolderSection = () => {
    if (foldersLoading) {
      return (
        <div className="flex justify-center py-6">
          <div className="animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-white"></div>
        </div>
      );
    }

    if (folders.length === 0) {
      return <p className="text-gray-400 py-4">フォルダーがありません</p>;
    }

    return (
      <div className="grid grid-cols-2 gap-4">
        {folders.map((folder, index) => (
          <div
            key={index}
            className="flex items-center p-3 bg-gray-900 rounded-md border border-gray-800 hover:bg-gray-800 cursor-pointer"
            onClick={() => (window.location.href = `/admin/documents/folder/${folder}`)}
          >
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
            <span>{folder}</span>
          </div>
        ))}
      </div>
    );
  };

  return (
    <AdminLayout title="ドキュメント管理">
      <div className="flex flex-col h-full">
        <div className="mb-6">
          <h1 className="text-2xl font-bold mb-4">ドキュメント</h1>

          {/* APIエラー表示 */}
          {apiError && (
            <div className="mb-4 p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
              <div className="flex items-center">
                <svg
                  className="w-5 h-5 mr-2 text-red-300"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
                <span>{apiError}</span>
              </div>
              <div className="mt-2 text-sm">
                <p>APIサーバーとの通信に問題があります。開発モードではダミーデータを使用します。</p>
              </div>
            </div>
          )}

          {/* 検索とアクションエリア */}
          <div className="flex items-center justify-between mb-6">
            <div className="flex items-center gap-4 ml-auto">
              <button
                className="bg-gray-900 rounded-xl w-12 h-12 flex items-center justify-center border border-gray-700"
                onClick={handleCreateImageFolder}
                title="フォルダ作成"
              >
                <svg
                  className="w-5 h-5 text-gray-400"
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
              </button>
              <button
                className="flex items-center px-4 py-2 bg-white text-[#0A0A0A] rounded-md hover:bg-gray-100"
                onClick={handleNewDocumentClick}
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
          {renderFolderSection()}
        </div>

        {/* フォルダ作成モーダル */}
        {showFolderModal && (
          <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div className="bg-[#0A0A0A] rounded-lg p-6 w-full max-w-md">
              <h3 className="text-xl font-bold text-center mb-12">ドキュメントフォルダを作成</h3>
              {error && (
                <div className="mb-4 p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
                  {error}
                </div>
              )}
              <input
                type="text"
                value={folderName}
                onChange={e => setFolderName(e.target.value)}
                className="w-full p-4 mb-8 bg-transparent border border-gray-700 rounded-md text-white"
                placeholder="フォルダ名"
                autoFocus
              />
              <div className="flex flex-col gap-4 items-center">
                <button
                  onClick={handleCreateFolder}
                  className="w-48 py-3 bg-[#3832A5] text-white rounded-md hover:bg-opacity-90 flex items-center justify-center"
                  disabled={!folderName.trim() || isCreating}
                >
                  {isCreating ? (
                    <div className="animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-white"></div>
                  ) : (
                    '作成'
                  )}
                </button>
                <button
                  onClick={handleCloseModal}
                  className="w-48 py-3 bg-gray-500 text-white rounded-md hover:bg-opacity-90"
                  disabled={isCreating}
                >
                  戻る
                </button>
              </div>
            </div>
          </div>
        )}

        {/* ブランチ作成確認モーダル */}
        {showBranchModal && (
          <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div className="bg-[#0A0A0A] rounded-lg p-6 w-full max-w-md">
              <h3 className="text-xl font-bold text-center mb-8">差分を作成しますか？</h3>
              {branchError && (
                <div className="mb-4 p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
                  {branchError}
                </div>
              )}
              <div className="flex flex-col gap-4 items-center">
                <button
                  onClick={handleCreateBranch}
                  className="w-48 py-3 bg-[#3832A5] text-white rounded-md hover:bg-opacity-90 flex items-center justify-center"
                  disabled={isBranchCreating}
                >
                  {isBranchCreating ? (
                    <div className="animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-white"></div>
                  ) : (
                    'はい'
                  )}
                </button>
                <button
                  onClick={handleCloseBranchModal}
                  className="w-48 py-3 bg-gray-500 text-white rounded-md hover:bg-opacity-90"
                  disabled={isBranchCreating}
                >
                  戻る
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
