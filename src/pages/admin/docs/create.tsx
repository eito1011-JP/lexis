import React, { useState } from 'react';
import Layout from '@theme/Layout';
import TiptapEditor from '@site/src/components/admin/editor/TiptapEditor';
import AdminLayout from '@site/src/components/admin/layout';
import { useSessionCheck } from '@site/src/hooks/useSessionCheck';

export default function AdminPage(): JSX.Element {
  useSessionCheck('/admin/login', false);

  const [content, setContent] = useState('<p>ここにドキュメントを作成してください...</p>');
  const [slug, setSlug] = useState('');
  const [sidebarLabel, setSidebarLabel] = useState('');

  const handleEditorChange = (html: string) => {
    setContent(html);
    console.log('エディタの内容が更新されました:', html);
  };

  const handleSave = () => {
    console.log('保存されたデータ:', {
      slug,
      sidebarLabel,
      content,
    });
    // ここに保存処理を追加
  };

  return (
    <AdminLayout title="新規ドキュメント作成">
      <div className="container mx-auto py-8">
        <div className="flex justify-between items-center mb-6">
          <div className="flex items-center gap-4">
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
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M15 19l-7-7 7-7"
                />
              </svg>
            </button>
            <h1 className="m-0">新規のドキュメント</h1>
          </div>

          <button
            className="px-5 py-2 bg-gray-400 text-black border-none rounded cursor-pointer"
            onClick={handleSave}
          >
            保存
          </button>
        </div>

        <div className="mb-5">
          <label htmlFor="slug" className="block mb-2 font-bold">
            執筆者名
          </label>
          <input
            id="slug"
            type="text"
            value={slug}
            onChange={e => setSlug(e.target.value)}
            className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white"
          />
        </div>

        <div className="mb-5">
          <label htmlFor="sidebarLabel" className="block mb-2 font-bold">
            タイトル
          </label>
          <input
            id="sidebarLabel"
            type="text"
            value={sidebarLabel}
            onChange={e => setSidebarLabel(e.target.value)}
            className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white"
          />
        </div>

        <div className="mb-5">
          <label htmlFor="public-select" className="block mb-2 font-bold">
            公開設定
          </label>
          <select
            name="public"
            id="public-select"
            className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white"
          >
            <option value="public">公開</option>
            <option value="private">非公開</option>
          </select>
        </div>

        <div className="grid grid-cols-12 gap-4 mt-8">
          <div className="col-span-6">
            <label htmlFor="body" className="block mb-2 font-bold">
              本文
            </label>
            <div className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white">
              <div className="card__body">
                <TiptapEditor initialContent={content} onChange={handleEditorChange} />
              </div>
            </div>
          </div>
          <div className="col-span-6">
            <label htmlFor="preview" className="block mb-2 font-bold">
              プレビュー
            </label>
            <div className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white">
              <div className="card__body">
                <div dangerouslySetInnerHTML={{ __html: content }} />
              </div>
            </div>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
