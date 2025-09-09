import React, { useState, useEffect, useCallback } from 'react';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import SlateEditor from '@/components/admin/editor/SlateEditor';

interface CategoryCreationFormProps {
  parentCategoryId?: number;
  onSuccess?: () => void;
  onCancel?: () => void;
  onNavigateAway?: () => void;
  onUnsavedChangesChange?: (hasUnsavedChanges: boolean) => void;
}

/**
 * カテゴリ新規作成フォームコンポーネント
 * 添付画像のデザインに基づいて実装
 */
export default function CategoryCreationForm({ 
  parentCategoryId, 
  onSuccess, 
  onCancel,
  onNavigateAway,
  onUnsavedChangesChange
}: CategoryCreationFormProps) {
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [isCreating, setIsCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

  // 未保存の変更を追跡
  useEffect(() => {
    const hasChanges = title.trim() !== '' || description.trim() !== '';
    setHasUnsavedChanges(hasChanges);
    if (onUnsavedChangesChange) {
      onUnsavedChangesChange(hasChanges);
    }
  }, [title, description, onUnsavedChangesChange]);

  // ブラウザタブ/ウィンドウを閉じる際の保護
  useEffect(() => {
    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
      if (hasUnsavedChanges) {
        e.preventDefault();
        e.returnValue = '';
      }
    };

    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [hasUnsavedChanges]);

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
      const payload = {
        title: title,
        description: description,
        parent_id: parentCategoryId || null,
        edit_pull_request_id: null,
        pull_request_edit_token: null,
      };

      await apiClient.post(API_CONFIG.ENDPOINTS.CATEGORIES.CREATE, payload);

      // 保存成功時は未保存状態をリセット
      setHasUnsavedChanges(false);
      if (onUnsavedChangesChange) {
        onUnsavedChangesChange(false);
      }

      onSuccess?.();
    } catch (error: any) {
      if (error.response?.status === 401) {
        setError(error.response?.message);
      } 
      else if (error.response?.status === 409) {
        setError(error.response?.message);
      }
      else if (error.response?.status === 422) {
        setError(error.response?.message);
      }
      else if (error.response?.status === 500) {
        setError(error.response?.message);
      }
      else {
        setError(error.response?.message);
      }
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

// ナビゲーション制御関数をエクスポート（親コンポーネントで使用するため）
export const useUnsavedChangesHandler = (hasUnsavedChanges: boolean) => {
  const [showModal, setShowModal] = useState(false);
  const [pendingNavigation, setPendingNavigation] = useState<(() => void) | null>(null);

  const handleNavigationRequest = useCallback((navigationFn: () => void) => {
    if (hasUnsavedChanges) {
      setPendingNavigation(() => navigationFn);
      setShowModal(true);
    } else {
      navigationFn();
    }
  }, [hasUnsavedChanges]);

  const handleConfirm = () => {
    setShowModal(false);
    if (pendingNavigation) {
      pendingNavigation();
      setPendingNavigation(null);
    }
  };

  const handleCancel = () => {
    setShowModal(false);
    setPendingNavigation(null);
  };

  return {
    showModal,
    handleNavigationRequest,
    handleConfirm,
    handleCancel
  };
};
