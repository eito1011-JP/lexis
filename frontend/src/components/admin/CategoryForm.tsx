import React, { useState, useEffect, useCallback } from 'react';
import SlateEditor from '@/components/admin/editor/SlateEditor';

export interface CategoryFormData {
  title: string;
  description: string;
}

interface CategoryFormProps {
  initialData?: CategoryFormData;
  onSubmit: (data: CategoryFormData) => Promise<void>;
  onCancel?: () => void;
  onUnsavedChangesChange?: (hasUnsavedChanges: boolean) => void;
  isSubmitting?: boolean;
  submitButtonText?: string;
  submittingText?: string;
}

/**
 * カテゴリフォームの純粋なUIコンポーネント
 * 作成・編集の両方で使用可能
 */
export default function CategoryForm({
  initialData = { title: '', description: '' },
  onSubmit,
  onCancel,
  onUnsavedChangesChange,
  isSubmitting = false,
  submitButtonText = '保存',
  submittingText = '保存中...'
}: CategoryFormProps) {
  const [title, setTitle] = useState(initialData.title);
  const [description, setDescription] = useState(initialData.description);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);

  // initialData が変更されたときに state を更新（空でない場合のみ）
  useEffect(() => {
    // 空でないデータが来た場合のみ更新
    if (initialData.title !== '' || initialData.description !== '') {
      setTitle(initialData.title);
      setDescription(initialData.description);
    }
  }, [initialData]);

  // 未保存の変更を追跡
  useEffect(() => {
    const hasChanges = title !== initialData.title || description !== initialData.description;
    setHasUnsavedChanges(hasChanges);
    if (onUnsavedChangesChange) {
      onUnsavedChangesChange(hasChanges);
    }
  }, [title, description, onUnsavedChangesChange, initialData]);

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

  const handleSubmit = async () => {
    if (isSubmitting) return;
    
    if (!title.trim()) {
      return;
    }

    try {
      await onSubmit({ title, description });
    } catch (error) {
      // エラーハンドリングは親コンポーネントに委譲
      console.error('フォーム送信エラー:', error);
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
        <div className="mb-6">
          <label className="block text-sm font-medium mb-2">タイトル</label>
          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="これは新しいカテゴリです"
            className="w-full px-3 py-2 bg-transparent border border-gray-600 rounded-md text-white placeholder-gray-500 focus:outline-none focus:border-blue-500"
            disabled={isSubmitting}
          />
        </div>

        <div className="mb-6">
          <label className="block text-sm font-medium mb-2">説明</label>
          <div className="w-full p-2.5 border border-gray-700 rounded bg-black text-white min-h-72">
            <SlateEditor
              initialContent={initialData.description}
              onChange={() => {}}
              onMarkdownChange={handleEditorChange}
              placeholder="ここにカテゴリの説明を入力してください"
            />
          </div>
        </div>

        {/* ボタン */}
        <div className="flex gap-4">
          <button
            onClick={handlePreview}
            className="px-6 py-2 bg-gray-700 hover:bg-gray-600 rounded-md text-white transition-colors"
            disabled={isSubmitting}
          >
            プレビュー
          </button>
          <button
            onClick={handleSubmit}
            disabled={isSubmitting || !title.trim()}
            className="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800 disabled:cursor-not-allowed rounded-md text-white transition-colors"
          >
            {isSubmitting ? submittingText : submitButtonText}
          </button>
          {onCancel && (
            <button
              onClick={onCancel}
              disabled={isSubmitting}
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
