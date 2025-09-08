import React, { useState } from 'react';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import SlateEditor from '@/components/admin/editor/SlateEditor';

interface CategoryCreationFormProps {
  parentCategoryId?: string;
  parentCategoryPath?: string;
  onSuccess?: (newCategory: any) => void;
  onCancel?: () => void;
}

/**
 * カテゴリ新規作成フォームコンポーネント
 * 添付画像のデザインに基づいて実装
 */
export default function CategoryCreationForm({ 
  parentCategoryId, 
  parentCategoryPath,
  onSuccess, 
  onCancel 
}: CategoryCreationFormProps) {
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [isCreating, setIsCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleEditorChange = (markdown: string) => {
    setDescription(markdown);
  };

  const handleSave = async () => {
    if (isCreating) return;
    
    if (!title.trim()) {
      setError('タイトルを入力してください');
      return;
    }

    setIsCreating(true);
    setError(null);

    try {
      // slugを自動生成（タイトルから）
      const slug = title.toLowerCase()
        .replace(/[^\w\s-]/g, '') // 特殊文字を削除
        .replace(/\s+/g, '-') // スペースをハイフンに
        .trim();

      const payload = {
        category_path: parentCategoryPath || null,
        slug: slug,
        sidebar_label: title,
        description: description,
      };

      const response = await apiClient.post(API_CONFIG.ENDPOINTS.CATEGORIES.CREATE, payload);
      
      if (onSuccess) {
        onSuccess(response);
      }
    } catch (err) {
      console.error('カテゴリ作成エラー:', err);
      setError(err instanceof Error ? err.message : '不明なエラーが発生しました');
    } finally {
      setIsCreating(false);
    }
  };

  const handlePreview = () => {
    // プレビュー機能は今回は実装しない
    console.log('プレビュー機能は未実装です');
  };

  return (
    <div className="text-white min-h-full">
      {/* ヘッダー部分 */}
      <div className="border-b border-gray-700 p-6">
        <div className="flex items-center text-sm text-gray-400 mb-4">
          <span>🏠</span>
          <span className="mx-2">›</span>
          <span>人事制度</span>
        </div>
        
        <div className="mb-6">
          <label className="block text-sm font-medium mb-2">タイトル</label>
          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="これは新しいカテゴリです"
            className="w-full px-3 py-2 bg-transparent border border-gray-600 rounded-md text-white placeholder-gray-500 focus:outline-none focus:border-blue-500"
          />
        </div>

        <div className="mb-6">
          <label className="block text-sm font-medium mb-2">説明</label>
          <div className="w-full p-2.5 border border-gray-700 rounded bg-black text-white min-h-72">
            <SlateEditor
              initialContent=""
              onChange={() => {}}
              onMarkdownChange={handleEditorChange}
              placeholder="ここにカテゴリの説明を入力してください"
            />
          </div>
        </div>

        {/* エラーメッセージ */}
        {error && (
          <div className="mb-4 p-3 bg-red-900/50 border border-red-700 rounded-md text-red-300">
            {error}
          </div>
        )}

        {/* ボタン */}
        <div className="flex gap-4">
          <button
            onClick={handlePreview}
            className="px-6 py-2 bg-gray-700 hover:bg-gray-600 rounded-md text-white transition-colors"
          >
            プレビュー
          </button>
          <button
            onClick={handleSave}
            disabled={isCreating}
            className="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800 disabled:cursor-not-allowed rounded-md text-white transition-colors"
          >
            {isCreating ? '保存中...' : '保存'}
          </button>
          {onCancel && (
            <button
              onClick={onCancel}
              disabled={isCreating}
              className="px-6 py-2 bg-gray-600 hover:bg-gray-500 disabled:bg-gray-700 disabled:cursor-not-allowed rounded-md text-white transition-colors"
            >
              キャンセル
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
