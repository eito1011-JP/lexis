import { useState, useEffect } from 'react';
import AdminLayout from '@/components/admin/layout';
import { client } from '@/api/client';
import { useNavigate, useParams } from 'react-router-dom';
import SlateEditor from '@/components/admin/editor/SlateEditor';
import { Breadcrumb } from '@/components/common/Breadcrumb';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { createDocumentSchema, CreateDocumentFormData } from '@/schemas';

// エラー型の定義
interface ApiError {
  message?: string;
}

// カテゴリ型の定義
interface Category {
  id: number;
  title: string;
  name?: string;
  path?: string;
  breadcrumbs?: Array<{id: number; title: string}>;
}

export default function CreateDocumentPage(): JSX.Element {
  const navigate = useNavigate();
  const { categoryEntityId: categoryEntityIdParam } = useParams<{ categoryEntityId: string }>();
  const [isLoading, setIsLoading] = useState(true);

  const [categoryEntityId, setCategoryEntityId] = useState<number | null>(null);
  const [selectedCategory, setSelectedCategory] = useState<Category | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
    setValue,
    watch,
  } = useForm<CreateDocumentFormData>({
    resolver: zodResolver(createDocumentSchema),
    mode: 'onBlur',
    defaultValues: {
      title: '',
      description: '',
    },
  });

  const title = watch('title');
  const description = watch('description');

  // URLパスからcategoryIdを取得し、そのカテゴリ情報を取得
  useEffect(() => {
    const fetchCategoryDetail = async () => {
      if (!categoryEntityIdParam) {
        console.error('categoryId is missing from URL path');
        setIsLoading(false);
        return;
      }

      try {
        const id = parseInt(categoryEntityIdParam);
        setCategoryEntityId(id);
        setValue('category_entity_id', id);
        const response = await client.category_entities._entityId(id).$get();
        setSelectedCategory(response.category);
      } catch (error) {
        console.error('カテゴリ詳細取得エラー:', error);
      } finally {
        setIsLoading(false);
      }
    };

    fetchCategoryDetail();
  }, [categoryEntityIdParam, setValue]);

  const onSubmit = async (data: CreateDocumentFormData) => {
    if (isSubmitting) return;

    try {
      setIsSubmitting(true);

      // RESTfulなドキュメント作成APIを呼び出す
      await client.document_entities.$post({
        body: {
          title: data.title.trim(),
          description: data.description.trim(),
          category_entity_id: data.category_entity_id,
        } as any
      });

      alert('ドキュメントが作成されました');
      
      // 成功したらドキュメント一覧ページに戻る
      navigate('/documents');
    } catch (error: unknown) {
      console.error('ドキュメント作成エラー:', error);
      const apiError = error as ApiError;
      alert(`ドキュメントの作成に失敗しました: ${apiError.message || '不明なエラー'}`);
    } finally {
      setIsSubmitting(false);
    }
  };

  // ローディング中の表示
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
    <AdminLayout title="ドキュメント作成" sidebar={true} showDocumentSideContent={true}>
      <div className="text-white min-h-full">
        {/* ヘッダー部分 */}
        <div className="border-b border-gray-700 p-6">
          <div className="mb-4">
            <Breadcrumb 
              breadcrumbs={selectedCategory?.breadcrumbs}
              homeLink="/documents"
            />
          </div>
          
          <div className="mb-6">
            <label className="block text-sm font-medium mb-2">タイトル</label>
            <input
              type="text"
              {...register('title')}
              placeholder="ドキュメントのタイトルを入力してください"
              className="w-full px-3 py-2 bg-transparent border border-gray-600 rounded-md text-white placeholder-gray-500 focus:outline-none focus:border-blue-500"
              disabled={isSubmitting}
            />
            {errors.title && (
              <p className="text-red-500 text-sm mt-1">{errors.title.message}</p>
            )}
          </div>

          <div className="mb-6">
            <label className="block text-sm font-medium mb-2">本文</label>
            <div className="w-full p-2.5 border border-gray-700 rounded bg-black text-white min-h-72">
              <SlateEditor
                initialContent={description}
                onChange={() => {}}
                onMarkdownChange={(markdown: string) => setValue('description', markdown)}
                placeholder="ここにドキュメントの内容を入力してください"
              />
            </div>
            {errors.description && (
              <p className="text-red-500 text-sm mt-1">{errors.description.message}</p>
            )}
          </div>

          {/* ボタン */}
          <div className="flex gap-4">
            <button
              type="button"
              onClick={() => console.log('プレビュー機能は未実装です')}
              className="px-6 py-2 bg-gray-700 hover:bg-gray-600 rounded-md text-white transition-colors"
              disabled={isSubmitting}
            >
              プレビュー
            </button>
            <button
              type="button"
              onClick={handleSubmit(onSubmit)}
              disabled={isSubmitting || !title?.trim()}
              className="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800 disabled:cursor-not-allowed rounded-md text-white transition-colors"
            >
              {isSubmitting ? '保存中...' : '保存'}
            </button>
            <button
              type="button"
              onClick={() => navigate('/documents')}
              disabled={isSubmitting}
              className="px-6 py-2 bg-gray-600 hover:bg-gray-500 disabled:bg-gray-700 disabled:cursor-not-allowed rounded-md text-white transition-colors"
            >
              キャンセル
            </button>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}