import { useState, useEffect } from 'react';
import { useLocation } from 'react-router-dom';
import AdminLayout from '@/components/admin/layout';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import SlateEditor from '@/components/admin/editor/SlateEditor';
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

// ドキュメントデータの型定義
interface DocumentData {
  id: number;
  slug: string;
  sidebar_label: string;
  content: string;
  file_order: number;
  is_public: boolean;
  last_edited_by: string | null;
  source: 'md_file' | 'database';
}

export default function EditDocumentPage(): JSX.Element {
  const location = useLocation();
  const { isLoading } = useSessionCheck('/login', false);
  const [label, setLabel] = useState('');
  const [content, setContent] = useState('');
  const [publicOption, setPublicOption] = useState('公開する');
  const [reviewer, setReviewer] = useState('');
  const [reviewers, setReviewers] = useState<User[]>([]);
  const [documentSlug, setDocumentSlug] = useState('');
  const [fileOrder, setFileOrder] = useState<number | ''>('');
  const [error, setError] = useState<string | null>(null);
  const [invalidSlug, setInvalidSlug] = useState<string | null>(null);
  const [documentLoading, setDocumentLoading] = useState(true);
  const [showReviewerModal, setShowReviewerModal] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [filteredReviewers, setFilteredReviewers] = useState<User[]>([]);
  const [reviewerName, setReviewerName] = useState('');
  const [documentId, setDocumentId] = useState<number | null>(null);

  // URLからslugとcategoryを抽出
  const pathname = location.pathname;
  // '/documents/a/b/c/edit' → ['', 'documents', 'a', 'b', 'c', 'edit']
  const pathParts = pathname.split('/');
  // 最後のeditを除去して、最後の要素がslug
  const slug = pathParts[pathParts.length - 2];
  // 残りの部分がカテゴリパス (documentsとslugとeditを除く)
  const category = pathParts.slice(2, pathParts.length - 2).join('/');

  useEffect(() => {
    const fetchDocument = async () => {
      if (!slug) return;
      try {
        setDocumentLoading(true);
        const endpoint = `${API_CONFIG.ENDPOINTS.DOCUMENTS.GET_DOCUMENT_BY_CATEGORY_PATH}`;

        // クエリパラメータとしてcategory_pathを渡す
        const response = await apiClient.get(`${endpoint}/${category ? `${category}/${slug}` : slug}`);

        console.log('response', response);
        if (response) {
          // 取得したデータをフォームにセット
          setDocumentId(response.id);
          setDocumentSlug(response.slug || '');
          setLabel(response.sidebar_label || '');
          setContent(response.content || '');
          setPublicOption(response.is_public ? '公開する' : '公開しない');
          setFileOrder(response.file_order || '');
        }
      } catch (error) {
        console.error('ドキュメント取得エラー:', error);
        setError('ドキュメントの取得に失敗しました');
      } finally {
        setDocumentLoading(false);
      }
    };

    if (slug) {
      fetchDocument();
    }
  }, [slug, category]);

  // 検索クエリに基づいてレビュアーをフィルタリング
  useEffect(() => {
    if (searchQuery) {
      const filtered = reviewers.filter(user =>
        user.email.toLowerCase().includes(searchQuery.toLowerCase())
      );
      setFilteredReviewers(filtered);
    } else {
      setFilteredReviewers(reviewers);
    }
  }, [searchQuery, reviewers]);

  // 予約語やルーティングで使用される特殊パターン
  const reservedSlugs = ['create', 'edit', 'new', 'delete', 'update'];

  // slugのバリデーション関数
  const validateSlug = (value: string) => {
    // 空の場合はエラーなし（必須チェックは別で行う）
    if (!value.trim()) {
      setInvalidSlug(null);
      return;
    }

    // 予約語チェック
    if (reservedSlugs.includes(value.toLowerCase())) {
      setInvalidSlug(`"${value}" は予約語のため使用できません`);
      return;
    }

    // URLで問題になる文字をチェック
    if (!/^[a-z0-9-]+$/i.test(value)) {
      setInvalidSlug('英数字とハイフン(-)のみ使用できます');
      return;
    }

    setInvalidSlug(null);
  };

  // slugの変更ハンドラー
  const handleSlugChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setDocumentSlug(value);
    validateSlug(value);
  };

  const handleEditorChange = (html: string) => {
    setContent(html);
  };

  const handleSave = async () => {
    try {
      if (!label) {
        alert('タイトルを入力してください');
        return;
      }

      if (!documentId) {
        alert('ドキュメントIDが見つかりません');
        return;
      }

      // ドキュメント編集APIを呼び出す
      const response = await apiClient.put(`${API_CONFIG.ENDPOINTS.DOCUMENTS.UPDATE}/${documentSlug}`, {
        category,
        sidebar_label: label,
        content,
        is_public: publicOption === '公開する',
        slug: documentSlug,
        file_order: fileOrder === '' ? null : Number(fileOrder),
      });

      if (response.success) {
        alert('ドキュメントが編集されました');
        // 成功したら一覧ページに戻る
        window.location.href = category ? `/admin/documents/${category}` : '/admin/documents';
      } else {
        throw new Error(response.message || '不明なエラーが発生しました');
      }
    } catch (error: unknown) {
      console.error('ドキュメント編集エラー:', error);
      const apiError = error as ApiError;
      alert(`ドキュメントの編集に失敗しました: ${apiError.message || '不明なエラー'}`);
    }
  };

  // セッション確認中またはドキュメント読み込み中はローディング表示
  if (isLoading || documentLoading) {
    return (
      <AdminLayout title="読み込み中...">
        <div className="flex flex-col items-center justify-center h-full">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout title="ドキュメント編集">
      <div className="max-w-4xl mx-auto">
        <div className="mb-6">
          <h1 className="text-2xl font-bold mb-4">ドキュメント編集</h1>
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
          </div>

          <div className="mb-6">
            <label className="block mb-2 font-bold">Slug</label>
            <input
              type="text"
              value={documentSlug}
              onChange={handleSlugChange}
              className={`w-full p-2.5 border ${invalidSlug ? 'border-red-500' : 'border-gray-700'} rounded bg-transparent text-white`}
              placeholder="slugを入力してください"
            />
            {invalidSlug && <p className="mt-1 text-red-500 text-sm">{invalidSlug}</p>}
          </div>

          <div className="mb-6">
            <label className="block mb-2 font-bold">表示順序</label>
            <input
              type="number"
              value={fileOrder}
              onChange={e => {
                const value = e.target.value;
                if (value === '') {
                  setFileOrder('');
                } else {
                  const num = Number(value);
                  if (!isNaN(num) && isFinite(num)) {
                    setFileOrder(num);
                  }
                }
              }}
              className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white"
              placeholder="表示順序を入力してください"
              min="1"
            />
          </div>

          <div className="mb-6">
            <label className="block mb-2 font-bold">タイトル</label>
            <input
              type="text"
              value={label}
              onChange={e => setLabel(e.target.value)}
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

          <div className="gap-6 mt-8">
            <div>
              <label className="block mb-2 font-bold">本文</label>
              <div className="w-full p-2.5 border border-gray-700 rounded bg-black text-white min-h-72">
                <SlateEditor
                  initialContent={content}
                  onChange={handleEditorChange}
                  placeholder="ここにドキュメントを作成してください"
                />
              </div>
            </div>
          </div>

          <div className="flex flex-col items-center gap-4 mt-8">
            <button
              className="px-4 py-2 bg-[#3832A5] text-white rounded hover:bg-opacity-80 border-none w-45"
              onClick={handleSave}
              disabled={!!invalidSlug}
            >
              保存
            </button>
          </div>
        </div>
      </div>

      {/* レビュー担当者選択モーダル */}
      {showReviewerModal && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-gray-900 p-6 rounded-lg w-full max-w-md">
            <h2 className="text-xl font-bold mb-4">レビュー担当者を選択</h2>
            <div className="mb-4">
              <input
                type="text"
                placeholder="ユーザーを検索..."
                className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white"
                value={searchQuery}
                onChange={e => setSearchQuery(e.target.value)}
              />
            </div>

            <div className="max-h-60 overflow-y-auto mb-4">
              {filteredReviewers.length > 0 ? (
                filteredReviewers.map(user => (
                  <div
                    key={user.id}
                    className="p-2 hover:bg-gray-800 rounded cursor-pointer flex items-center"
                  >
                    <div className="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center mr-2">
                      {user.email.charAt(0).toUpperCase()}
                    </div>
                    <span>{user.email}</span>
                  </div>
                ))
              ) : (
                <div className="text-center py-4 text-gray-400">
                  {searchQuery ? '検索結果がありません' : 'ユーザーを読み込み中...'}
                </div>
              )}
            </div>

            <div className="flex justify-end">
              <button
                className="px-4 py-2 bg-gray-800 rounded-md hover:bg-gray-700 focus:outline-none"
                onClick={() => setShowReviewerModal(false)}
              >
                キャンセル
              </button>
            </div>
          </div>
        </div>
      )}
    </AdminLayout>
  );
}
