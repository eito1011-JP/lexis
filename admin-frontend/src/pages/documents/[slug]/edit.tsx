import { useState, useEffect } from 'react';
import { useParams, useNavigate, useLocation } from 'react-router-dom';
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

// ドキュメントデータの型定義
interface DocumentData {
  slug: string;
  label: string;
  content: string;
  position: number;
  isPublic: boolean;
  lastReviewedBy: string | null;
  lastEditedBy: string | null;
  description: string;
  source: 'md_file' | 'database';
}

export default function EditDocumentPage(): JSX.Element {
  const location = useLocation();
  const { isLoading } = useSessionCheck('/login', false);
  const [label, setLabel] = useState('');
  const [content, setContent] = useState('');
  const [publicOption, setPublicOption] = useState('公開する');
  const [reviewer, setReviewer] = useState('');
  const [users, setUsers] = useState<User[]>([]);
  const [usersLoading, setUsersLoading] = useState(true);
  const [documentSlug, setDocumentSlug] = useState('');
  const [displayOrder, setDisplayOrder] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [invalidSlug, setInvalidSlug] = useState<string | null>(null);
  const [documentLoading, setDocumentLoading] = useState(true);

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
      console.log('slug', slug);
      try {
        setDocumentLoading(true);
        const endpoint = `${API_CONFIG.ENDPOINTS.DOCUMENTS.GET_DOCUMENT_BY_SLUG}/${slug}`;

        // カテゴリパラメータがある場合はAPIリクエストに含める
        const response = await apiClient.get(endpoint + (category ? `?category=${category}` : ''));

        console.log('response', response);
        if (response) {
          // 取得したデータをフォームにセット
          setDocumentSlug(response.slug || '');
          setLabel(response.label || '');
          setContent(response.content || '');
          setPublicOption(response.isPublic ? '公開する' : '公開しない');
          setReviewer(response.reviewerEmail || '');
          setDisplayOrder(response.position?.toString() || '');
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

      // ドキュメント編集APIを呼び出す
      const response = await apiClient.put(API_CONFIG.ENDPOINTS.DOCUMENTS.EDIT_DOCUMENT, {
        category,
        label,
        content,
        isPublic: publicOption === '公開する', // 公開設定を真偽値に変換
        lastReviewedBy: reviewer || null, // レビュー担当者のメールアドレス
        slug: documentSlug,
        displayOrder,
      });

      if (response.success) {
        alert('ドキュメントが編集されました');
        // 成功したら一覧ページに戻る
        window.location.href = category ? `/documents/${category}` : '/documents';
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
            <div className="ml-auto">
              <button
                className="px-4 py-2 bg-[#3832A5] text-white rounded hover:bg-opacity-80 border-none"
                onClick={handleSave}
                disabled={!!invalidSlug}
              >
                保存
              </button>
            </div>
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
              value={displayOrder}
              onChange={e => setDisplayOrder(e.target.value)}
              className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white"
              placeholder="表示順序を入力してください"
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
                  initialContent={content}
                  onChange={handleEditorChange}
                  placeholder="ここにドキュメントを作成してください"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
