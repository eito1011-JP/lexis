import React, { useState } from 'react';
import AdminLayout from '@site/src/components/admin/layout';
import { useSessionCheck } from '@site/src/hooks/useSessionCheck';
import TiptapEditor from '@site/src/components/admin/editor/TiptapEditor';

export default function NewDocumentPage(): React.ReactElement {
  const { isLoading } = useSessionCheck('/admin/login', false);

  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [publicOption, setPublicOption] = useState('公開する');
  const [hierarchy, setHierarchy] = useState('');
  const [reviewer, setReviewer] = useState('');

  const handleEditorChange = (html: string) => {
    setContent(html);
  };

  const handleSave = () => {
    console.log('保存されたデータ:', {
      title,
      content,
      publicOption,
      hierarchy,
      reviewer,
    });
    // ここに保存処理を追加する
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
    <AdminLayout title="新規のドキュメント">
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
        <h1 className="text-2xl font-bold m-0">新規のドキュメント</h1>
        <div className="ml-auto">
          <button
            className="px-4 py-2 bg-white text-black rounded hover:bg-gray-200"
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
            onChange={e => setHierarchy(e.target.value)}
            className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white pr-10"
            placeholder="日本国憲法"
          />
          <button className="absolute right-3 top-1/2 transform -translate-y-1/2 text-white">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              className="h-5 w-5"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path
                fillRule="evenodd"
                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                clipRule="evenodd"
              />
            </svg>
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
        <label className="block mb-2 font-bold">レビュアー</label>
        <div className="relative">
          <textarea
            value={reviewer}
            onChange={e => setReviewer(e.target.value)}
            className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white pr-10"
            rows={2}
            placeholder="sample1@example.com"
          />
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

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-8">
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
        <div>
          <label className="block mb-2 font-bold">プレビュー</label>
          <div className="w-full p-2.5 border border-gray-700 rounded bg-black text-white min-h-72 overflow-auto">
            <div dangerouslySetInnerHTML={{ __html: content }} />
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
