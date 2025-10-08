import React, { useState, useEffect, useCallback } from 'react';
import SlateEditor from '@/components/admin/editor/SlateEditor';
import { createCategorySchema, CreateCategoryFormData } from '@/schemas';
import { useToast } from '@/contexts/ToastContext';
import { VALIDATION_ERROR } from '@/const/ErrorMessage';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';

// zodスキーマから型をエクスポート
export type { CreateCategoryFormData };

interface CategoryFormProps {
  initialData?: Partial<CreateCategoryFormData>;
  onSubmit: (data: CreateCategoryFormData) => Promise<void>;
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
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const { show } = useToast();

  const {
    register,
    handleSubmit,
    control,
    formState: { errors, isDirty },
    watch,
  } = useForm<CreateCategoryFormData>({
    resolver: zodResolver(createCategorySchema),
    mode: 'onBlur',
    defaultValues: {
      title: initialData.title || '',
      description: initialData.description || '',
    },
  });

  // フォームの値を監視
  const title = watch('title');
  const description = watch('description');

  // 未保存の変更を追跡
  useEffect(() => {
    setHasUnsavedChanges(isDirty);
    if (onUnsavedChangesChange) {
      onUnsavedChangesChange(isDirty);
    }
  }, [isDirty, onUnsavedChangesChange]);

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

  const onSubmitForm = async (data: CreateCategoryFormData) => {
    try {
      await onSubmit(data);
    } catch (error) {
      // エラーハンドリングは親コンポーネントに委譲
      console.error('フォーム送信エラー:', error);
      show({ message: VALIDATION_ERROR, type: 'error' });
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
        <form onSubmit={handleSubmit(onSubmitForm)}>
          <div className="mb-6">
            <label className="block text-sm font-medium mb-2">タイトル</label>
            <input
              type="text"
              placeholder="これは新しいカテゴリです"
              className={`w-full px-3 py-2 bg-transparent border rounded-md text-white placeholder-gray-500 focus:outline-none focus:border-blue-500 ${
                errors.title ? 'border-red-500' : 'border-gray-600'
              }`}
              disabled={isSubmitting}
              {...register('title')}
            />
            {errors.title && (
              <div className="mt-2">
                <p className="text-red-400 text-sm">{errors.title.message}</p>
              </div>
            )}
          </div>

          <div className="mb-6">
            <label className="block text-sm font-medium mb-2">説明</label>
            <Controller
              name="description"
              control={control}
              render={({ field }) => (
                <div className={`w-full p-2.5 border rounded bg-black text-white min-h-72 ${
                  errors.description ? 'border-red-500' : 'border-gray-700'
                }`}>
                  <SlateEditor
                    initialContent={initialData.description || ''}
                    onChange={() => {}}
                    onMarkdownChange={(markdown) => field.onChange(markdown)}
                    placeholder="ここにカテゴリの説明を入力してください"
                  />
                </div>
              )}
            />
            {errors.description && (
              <div className="mt-2">
                <p className="text-red-400 text-sm">{errors.description.message}</p>
              </div>
            )}
          </div>

          {/* ボタン */}
          <div className="flex gap-4">
            <button
              type="button"
              onClick={handlePreview}
              className="px-6 py-2 bg-gray-700 hover:bg-gray-600 rounded-md text-white transition-colors"
              disabled={isSubmitting}
            >
              プレビュー
            </button>
            <button
              type="submit"
              disabled={isSubmitting || !title || !description}
              className="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800 disabled:cursor-not-allowed rounded-md text-white transition-colors"
            >
              {isSubmitting ? submittingText : submitButtonText}
            </button>
            {onCancel && (
              <button
                type="button"
                onClick={onCancel}
                disabled={isSubmitting}
                className="px-6 py-2 bg-gray-600 hover:bg-gray-500 disabled:bg-gray-700 disabled:cursor-not-allowed rounded-md text-white transition-colors"
              >
                キャンセル
              </button>
            )}
          </div>
        </form>
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
