import AdminLayout from '@site/src/components/admin/layout';
import React, { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@site/src/hooks/useSessionCheck';
import { apiClient } from '@site/src/components/admin/api/client';
import { checkUserDraft } from '@site/api/admin/utils/git';

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
  const [apiError, setApiError] = useState<string | null>(null);
  const [showSubmitButton, setShowSubmitButton] = useState(false);
  const [showSubmitModal, setShowSubmitModal] = useState(false);
  const [showPrSubmitButton, setShowPrSubmitButton] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState<string | null>(null);

  useEffect(() => {
    // フォルダー一覧を取得
    const fetchFolders = async () => {
      try {
        const folders = await apiClient.get('/admin/documents/folders');

        if (folders.folders) {
          setFolders(folders.folders);
        }

        const hasUserDraft = await apiClient.get('/admin/git/check-diff');

        if (hasUserDraft.exists) {
          setShowPrSubmitButton(true);
        }
      } catch (err) {
        console.error('フォルダー取得エラー:', err);
        setApiError('フォルダーの取得に失敗しました');
      } finally {
        setFoldersLoading(false);
      }
    };

    fetchFolders();
  }, []);


  const handleCreateImageFolder = () => {
    setShowFolderModal(true);
  };

  const handleCloseModal = () => {
    setShowFolderModal(false);
    setFolderName('');
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


  // 差分提出のハンドラー
  const handleSubmitDiff = async () => {
    setIsSubmitting(true);
    setSubmitError(null);
    setSubmitSuccess(null);

    try {
      const response = await apiClient.post('/admin/git/create-pr', {
        title: '更新内容の提出',
        description: 'このPRはハンドブックの更新を含みます。',
      });

      if (response.success) {
        setShowSubmitModal(false);
        setShowSubmitButton(false);
        setSubmitSuccess('差分の提出が完了しました');
      } else {
        setSubmitError(response.message || '差分の提出に失敗しました');
      }
    } catch (err) {
      console.error('差分提出エラー:', err);
      setSubmitError('差分の提出中にエラーが発生しました');
    } finally {
      setIsSubmitting(false);
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

          {/* 差分提出の成功メッセージ */}
          {submitSuccess && (
            <div className="mb-4 p-3 bg-green-900/50 border border-green-800 rounded-md text-green-200">
              <div className="flex items-center">
                <svg
                  className="w-5 h-5 mr-2 text-green-300"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M5 13l4 4L19 7"
                  />
                </svg>
                <span>{submitSuccess}</span>
              </div>
            </div>
          )}

          {/* 差分提出のエラーメッセージ */}
          {submitError && (
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
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
                <span>{submitError}</span>
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
                className="flex items-center px-4 py-2 bg-white text-[#0A0A0A] rounded-md"
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
          {renderFolderSection()}
        </div>

        {/* 差分提出ボタン */}
          {showPrSubmitButton && (
          <div className="fixed bottom-8 right-8">
            <button
                onClick={handleSubmitDiff}
              className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
            >
              差分を提出する
            </button>
          </div>
        )}

        {/* 差分提出確認モーダル */}
        {showSubmitModal && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white p-6 rounded-lg max-w-md w-full">
              <h2 className="text-xl font-bold mb-4">差分の提出</h2>
              <p className="mb-4">現在の変更を提出しますか？</p>
              {submitError && (
                <div className="mb-4 p-3 bg-red-100 text-red-700 rounded">
                  {submitError}
                </div>
              )}
              <div className="flex justify-end gap-4">
                <button
                  onClick={() => setShowSubmitModal(false)}
                  className="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded"
                  disabled={isSubmitting}
                >
                  キャンセル
                </button>
                <button
                  onClick={handleSubmitDiff}
                  className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                  disabled={isSubmitting}
                >
                  {isSubmitting ? '提出中...' : '提出する'}
                </button>
              </div>
            </div>
          </div>
        )}

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
                  className="w-48 py-3 bg-[#3832A5] text-white rounded-md hover:bg-opacity-90 flex items-center border-none font-bold justify-center"
                  disabled={!folderName.trim() || isCreating}
                >
                  作成
                </button>
                <button
                  onClick={handleCloseModal}
                  className="w-48 py-3 bg-gray-500 text-white rounded-md border-none hover:bg-opacity-90 font-bold"
                  disabled={isCreating}
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
