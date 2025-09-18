import { useState, useEffect } from 'react';
import AdminLayout from '@/components/admin/layout';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { useNavigate, useParams } from 'react-router-dom';
import SlateEditor from '@/components/admin/editor/SlateEditor';

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
}

export default function CreateDocumentPage(): JSX.Element {
  const navigate = useNavigate();
  const { categoryId: categoryIdParam } = useParams<{ categoryId: string }>();
  const [isLoading, setIsLoading] = useState(true);

  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [selectedCategory, setSelectedCategory] = useState<Category | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [validationErrors, setValidationErrors] = useState<{[key: string]: string}>({});

  // URLパスからcategoryIdを取得し、そのカテゴリ情報を取得
  useEffect(() => {
    const fetchCategoryDetail = async () => {
      if (!categoryIdParam) {
        console.error('categoryId is missing from URL path');
        setIsLoading(false);
        return;
      }

      try {
        const id = parseInt(categoryIdParam);
        setCategoryId(id);
        // 特定のカテゴリの詳細情報を取得
        const response = await apiClient.get(`${API_CONFIG.ENDPOINTS.CATEGORIES.GET_DETAIL}/${id}`);
        
        // レスポンス構造に応じてカテゴリ情報を取得
        let categoryData = null;
        if (response.category) {
          categoryData = response.category;
        } else if (response.data) {
          categoryData = response.data;
        } else {
          categoryData = response;
        }
        setSelectedCategory(categoryData);
      } catch (error) {
        console.error('カテゴリ詳細取得エラー:', error);
      } finally {
        setIsLoading(false);
      }
    };

    fetchCategoryDetail();
  }, [categoryIdParam]);

  const handleSave = async () => {
    if (isSubmitting) return;


    // バリデーション
    const errors: {[key: string]: string} = {};
    if (!title.trim()) {
      errors.title = 'タイトルを入力してください';
    }
    if (!description.trim()) {
      errors.description = '本文を入力してください';
    }
    if (!categoryId) {
      alert('カテゴリが選択されていません。');
      return;
    }

    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
      return;
    }

    setValidationErrors({});

    try {
      setIsSubmitting(true);

      // APIリクエストのペイロードを構築
      const payload: any = {
        title: title.trim(),
        description: description.trim(),
        category_id: categoryId,
      };

      // プルリクエスト編集関連の処理（必要に応じて）
      const pullRequestEditToken = localStorage.getItem('pullRequestEditToken');
      if (pullRequestEditToken) {
        payload.pull_request_edit_token = pullRequestEditToken;
      }

      // ドキュメント作成APIを呼び出す（新エンドポイント）
      await apiClient.post(API_CONFIG.ENDPOINTS.DOCUMENT_VERSIONS.CREATE, payload);

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
          <div className="flex items-center text-sm text-gray-400 mb-4">
            <span>🏠</span>
            <span className="mx-2">›</span>
            {selectedCategory && (
              <>
                <span>{selectedCategory.title || selectedCategory.name}</span>
              </>
            )}

          </div>
          
          <div className="mb-6">
            <label className="block text-sm font-medium mb-2">タイトル</label>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder="ドキュメントのタイトルを入力してください"
              className="w-full px-3 py-2 bg-transparent border border-gray-600 rounded-md text-white placeholder-gray-500 focus:outline-none focus:border-blue-500"
              disabled={isSubmitting}
            />
            {validationErrors.title && (
              <p className="text-red-500 text-sm mt-1">{validationErrors.title}</p>
            )}
          </div>

          <div className="mb-6">
            <label className="block text-sm font-medium mb-2">本文</label>
            <div className="w-full p-2.5 border border-gray-700 rounded bg-black text-white min-h-72">
              <SlateEditor
                initialContent={description}
                onChange={() => {}}
                onMarkdownChange={(markdown: string) => setDescription(markdown)}
                placeholder="ここにドキュメントの内容を入力してください"
              />
            </div>
            {validationErrors.description && (
              <p className="text-red-500 text-sm mt-1">{validationErrors.description}</p>
            )}
          </div>

          {/* ボタン */}
          <div className="flex gap-4">
            <button
              onClick={() => console.log('プレビュー機能は未実装です')}
              className="px-6 py-2 bg-gray-700 hover:bg-gray-600 rounded-md text-white transition-colors"
              disabled={isSubmitting}
            >
              プレビュー
            </button>
            <button
              onClick={handleSave}
              disabled={isSubmitting || !title.trim()}
              className="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800 disabled:cursor-not-allowed rounded-md text-white transition-colors"
            >
              {isSubmitting ? '保存中...' : '保存'}
            </button>
            <button
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