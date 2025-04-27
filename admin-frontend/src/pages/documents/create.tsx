import React, { useState, useEffect } from 'react';
import AdminLayout from '@/components/admin/layout';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import TiptapEditor from '@/components/admin/editor/TiptapEditor';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';

// ユーザー型定義を追加
interface User {
  id: string;
  email: string;
}

// エラー型の定義を追加
interface ApiError {
  message?: string;
}

export default function CreateDocumentPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/login', false);

  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [publicOption, setPublicOption] = useState('公開する');
  const [hierarchy, setHierarchy] = useState('');
  const [reviewer, setReviewer] = useState('');
  const [isHierarchyModalOpen, setIsHierarchyModalOpen] = useState(false);
  const [folders, setFolders] = useState<string[]>([]);
  const [foldersLoading, setFoldersLoading] = useState(true);
  const [selectedFolder, setSelectedFolder] = useState<string | null>(null);
  const [users, setUsers] = useState<User[]>([]);
  const [usersLoading, setUsersLoading] = useState(true);

  useEffect(() => {
    // フォルダ一覧を取得
    const fetchFolders = async () => {
      try {
        const response = await apiClient.get('/admin/documents/folders');
        console.log('フォルダ取得レスポンス:', response);
        if (response.folders) {
          setFolders(response.folders);
        }
      } catch (err) {
        console.error('フォルダ取得エラー:', err);
      } finally {
        setFoldersLoading(false);
      }
    };

    fetchFolders();

    // ユーザー一覧を取得
    const fetchUsers = async () => {
      try {
        const response = await apiClient.get('/admin/users');
        if (response.users) {
          setUsers(response.users);
        }
      } catch (err) {
        console.error('ユーザー取得エラー:', err);
      } finally {
        setUsersLoading(false);
      }
    };

    fetchUsers();
  }, []);

  const handleEditorChange = (html: string) => {
    setContent(html);
  };

  const handleSave = async () => {
    try {
      if (!title) {
        alert('タイトルを入力してください');
        return;
      }

      if (!hierarchy) {
        alert('階層を選択してください');
        return;
      }

      // ドキュメント作成APIを呼び出す
      const response = await apiClient.post(API_CONFIG.ENDPOINTS.DOCUMENTS.CREATE_DOCUMENT, {
        title,
        content,
        file_path: hierarchy, // 階層情報をファイルパスとして使用
        is_public: publicOption === '公開する', // 公開設定を真偽値に変換
        reviewer_email: reviewer || null, // レビュー担当者のメールアドレス
      });

      if (response.success) {
        alert('ドキュメントが作成されました');
        // 成功したら一覧ページに戻る
        window.location.href = '/admin/documents';
      } else {
        throw new Error(response.message || '不明なエラーが発生しました');
      }
    } catch (error: unknown) {
      console.error('ドキュメント作成エラー:', error);
      const apiError = error as ApiError;
      alert(`ドキュメントの作成に失敗しました: ${apiError.message || '不明なエラー'}`);
    }
  };

  const handleSelectFolder = (folder: string) => {
    setSelectedFolder(folder);
  };

  const handleConfirmHierarchy = () => {
    if (selectedFolder) {
      setHierarchy(selectedFolder);
      setIsHierarchyModalOpen(false);
    }
  };

  // フォルダを複数列に分割する関数
  const renderFolderList = () => {
    if (foldersLoading) {
      return (
        <div className="p-4 text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-white mx-auto"></div>
          <p className="mt-2 text-gray-400">フォルダ読み込み中...</p>
        </div>
      );
    }

    if (folders.length === 0) {
      return <div className="p-4 text-center text-gray-400">フォルダが存在しません</div>;
    }

    // 画像のような2列表示のためにフォルダを分割
    const midPoint = Math.ceil(folders.length / 2);
    const leftColumnFolders = folders.slice(0, midPoint);
    const rightColumnFolders = folders.slice(midPoint);

    return (
      <div className="grid grid-cols-2 gap-4">
        <div className="p-4">
          {leftColumnFolders.map((folder, index) => (
            <div key={`left-${index}`} className="flex items-center mb-4">
              <button
                className={`w-full text-left p-2 rounded flex items-center ${
                  selectedFolder === folder
                    ? 'bg-transparent border-[#3832A5] text-[#FFFFFF]'
                    : 'bg-transparent hover:bg-[#3832A5]/50 border-[#B1B1B1] text-[#FFFFFF]'
                } border border-solid border-2`}
                onClick={() => handleSelectFolder(folder)}
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
                {folder}
              </button>
            </div>
          ))}
        </div>
        <div className="p-4">
          {rightColumnFolders.map((folder, index) => (
            <div key={`right-${index}`} className="flex items-center mb-4">
              <button
                className={`w-full text-left p-2 rounded flex items-center ${
                  selectedFolder === folder
                    ? 'bg-transparent border-[#3832A5] text-[#FFFFFF]'
                    : 'bg-transparent hover:bg-[#3832A5]/50 border-[#B1B1B1] text-[#FFFFFF]'
                } border border-solid border-3`}
                onClick={() => handleSelectFolder(folder)}
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
                {folder}
              </button>
            </div>
          ))}
        </div>
      </div>
    );
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
    <AdminLayout title="ドキュメント作成">
      <div className="max-w-4xl mx-auto">
        <div className="mb-6">
          <h1 className="text-2xl font-bold mb-4">ドキュメント作成</h1>
          <div className="flex items-center gap-4 mb-6">
            <button
              className="bg-gray-900 rounded-xl w-12 h-12 flex items-center justify-center border border-gray-700"
              onClick={() => window.history.back()}
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
                  d="M15 19l-7-7 7-7"
                />
              </svg>
            </button>
            <div className="ml-auto">
              <button
                className="px-4 py-2 bg-[#3832A5] text-white rounded hover:bg-opacity-80 border-none"
                onClick={handleSave}
              >
                保存
              </button>
            </div>
          </div>
          <div className="mb-6">
            <label className="block mb-2 font-bold">階層</label>
            <div className="relative">
              <input
                type="text"
                value={hierarchy}
                readOnly
                className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white pr-24 cursor-pointer"
                placeholder="階層を選択してください"
                onClick={() => setIsHierarchyModalOpen(true)}
              />
              <button
                className="absolute right-2 top-1/2 transform -translate-y-1/2 px-4 py-1.5 bg-[#3832A5] text-white rounded hover:bg-opacity-80 text-sm border-none"
                onClick={() => setIsHierarchyModalOpen(true)}
              >
                選択
              </button>
            </div>
          </div>

          <div className="mb-6">
            <label className="block mb-2 font-bold">タイトル</label>
            <input
              type="text"
              value={title}
              onChange={e => setTitle(e.target.value)}
              className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white"
              placeholder="タイトルを入力してください"
            />
          </div>

          <div className="mb-6">
            <label className="block mb-2 font-bold">公開設定</label>
            <div className="relative">
              <select
                value={publicOption}
                onChange={e => setPublicOption(e.target.value)}
                className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white appearance-none pr-10"
              >
                <option value="公開する">公開する</option>
                <option value="公開しない">公開しない</option>
              </select>
              <div className="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                <svg
                  className="w-5 h-5 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M19 9l-7 7-7-7"
                  ></path>
                </svg>
              </div>
            </div>
          </div>

          <div className="mb-6">
            <label className="block mb-2 font-bold">レビュー担当者</label>
            <div className="relative">
              {usersLoading ? (
                <div className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white">
                  <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mr-2 inline-block"></div>
                  <span className="text-gray-400">ユーザー読み込み中...</span>
                </div>
              ) : (
                <select
                  value={reviewer}
                  onChange={e => setReviewer(e.target.value)}
                  className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white appearance-none"
                >
                  <option value="">レビュー担当者を選択してください</option>
                  {users.map(user => (
                    <option key={user.id} value={user.email}>
                      {user.email}
                    </option>
                  ))}
                </select>
              )}
              <div className="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                <svg
                  className="w-5 h-5 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M19 9l-7 7-7-7"
                  ></path>
                </svg>
              </div>
            </div>
          </div>

          <div className="gap-6 mt-8">
            <div>
              <label className="block mb-2 font-bold">本文</label>
              <div className="w-full p-2.5 border border-gray-700 rounded bg-black text-white min-h-72">
                <TiptapEditor
                  initialContent=""
                  onChange={handleEditorChange}
                  placeholder="ここにドキュメントを作成してください"
                />
              </div>
            </div>
          </div>

          {isHierarchyModalOpen && (
            <div className="fixed inset-0 bg-[#B1B1B1]/50 flex items-center justify-center z-50">
              <div className="bg-[#1A1A1A] p-6 rounded-lg w-full max-w-2xl">
                <div className="flex justify-between items-center mb-4">
                  <h2 className="text-xl font-bold">階層選択</h2>
                </div>
                {renderFolderList()}
                <div className="px-4 flex justify-end gap-4 mt-6">
                  <button
                    onClick={() => setIsHierarchyModalOpen(false)}
                    className="border-none px-4 py-2 bg-[#B1B1B1] text-white rounded hover:bg-gray-700"
                  >
                    戻る
                  </button>
                  <button
                    onClick={handleConfirmHierarchy}
                    className="border-none px-4 py-2 bg-[#3832A5] text-white rounded hover:bg-opacity-80"
                    disabled={!selectedFolder}
                  >
                    選択
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </AdminLayout>
  );
}
