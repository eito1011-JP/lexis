import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useParams } from 'react-router-dom';
import { fetchPullRequestDetail, type PullRequestDetailResponse } from '@/api/pullRequestHelpers';
import { client } from '@/api/client';
import SlateEditor from '@/components/admin/editor/SlateEditor';
import { formatDistanceToNow } from 'date-fns';
import ja from 'date-fns/locale/ja';
import { PULL_REQUEST_STATUS } from '@/constants/pullRequestStatus';
import { Merge } from '@/components/icon/common/Merge';
import { Merged } from '@/components/icon/common/Merged';
import { Closed } from '@/components/icon/common/Closed';
import { Toast } from '@/components/admin/Toast';

// 差分データの型定義
type DiffItem = {
  id: number;
  slug: string;
  sidebar_label: string;
  description?: string;
  title?: string;
  content?: string;
  position?: number;
  file_order?: number;
  parent_entity_id?: number;
  category_id?: number;
  status: string;
  user_branch_id: number;
  created_at: string;
  updated_at: string;
};

// ステータスバナーコンポーネント
const StatusBanner: React.FC<{
  status: string;
  authorNickname: string;
  createdAt: string;
  conflict: boolean;
  title: string;
}> = ({ status, authorNickname, createdAt, conflict, title }) => {
  let button;
  switch (true) {
    case conflict:
      button = (
        <button
          type="button"
          className="flex items-center px-7 py-3 rounded-full bg-[#DA3633] focus:outline-none"
          disabled
        >
          <Closed className="w-5 h-5 mr-2" />
          <span className="text-white text-md font-bold">コンフリクト</span>
        </button>
      );
      break;
    case status === PULL_REQUEST_STATUS.MERGED:
      button = (
        <button
          type="button"
          className="flex items-center px-7 py-3 rounded-full bg-[#3832A5] focus:outline-none"
          disabled
        >
          <Merged className="w-5 h-5 mr-2" />
          <span className="text-white text-md font-bold">反映済み</span>
        </button>
      );
      break;
    case status === PULL_REQUEST_STATUS.OPENED:
      button = (
        <button
          type="button"
          className="flex items-center px-7 py-3 rounded-full bg-[#1B6E2A] focus:outline-none"
          disabled
        >
          <Merge className="w-5 h-5 mr-2" />
          <span className="text-white text-md font-bold">未対応</span>
        </button>
      );
      break;
    case status === PULL_REQUEST_STATUS.CLOSED:
      button = (
        <button
          type="button"
          className="flex items-center px-7 py-3 rounded-full bg-[#DA3633] focus:outline-none"
          disabled
        >
          <Closed className="w-5 h-5 mr-2" />
          <span className="text-white text-md font-bold">取り下げ</span>
        </button>
      );
      break;
    default:
      button = null;
  }
  return (
    <div className={`mb-10 rounded-lg`}>
      {/* タイトル表示 */}
      <h1 className="text-3xl font-bold text-white mb-4">{title}</h1>
      <div className="flex items-center justify-start">
        {button}
        <span className="font-medium text-[#B1B1B1] ml-4">
          {authorNickname}さんが{' '}
          {formatDistanceToNow(new Date(createdAt), { addSuffix: true, locale: ja })}{' '}
          に変更を提出しました
        </span>
      </div>
    </div>
  );
};

export default function FixRequestPage(): JSX.Element {
  const [isLoading, setIsLoading] = useState(true);
  const { id } = useParams<{ id: string }>();

  const [pullRequestData, setPullRequestData] = useState<PullRequestDetailResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  // フォームデータの状態
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [documentVersions, setDocumentVersions] = useState<DiffItem[]>([]);
  const [documentCategories, setDocumentCategories] = useState<DiffItem[]>([]);

  // 既存の変更提案詳細データを取得
  useEffect(() => {
    const fetchData = async () => {
      if (!id) {
        setError('プルリクエストIDが指定されていません');
        setLoading(false);
        return;
      }

      try {
        setLoading(true);
        const data = await fetchPullRequestDetail(id);
        console.log('data', data);
        setPullRequestData(data);

        // フォームに既存のデータをセット
        setTitle(data.title || '');
        setDescription(data.description || '');
      } catch (err) {
        console.error('プルリクエスト詳細取得エラー:', err);
        setError('プルリクエスト詳細の取得に失敗しました');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [id]);

  // カテゴリのフィールド更新
  const handleCategoryChange = (index: number, field: string, value: any) => {
    setDocumentCategories(prev =>
      prev.map((cat, i) => (i === index ? { ...cat, [field]: value } : cat))
    );
  };

  // ドキュメントのフィールド更新
  const handleDocumentChange = (index: number, field: string, value: any) => {
    setDocumentVersions(prev =>
      prev.map((doc, i) => (i === index ? { ...doc, [field]: value } : doc))
    );
  };

  // 修正リクエスト送信
  const handleSendFixRequest = async () => {
    if (!id) return;

    setIsSubmitting(true);
    try {
      await client.pull_requests._id(parseInt(id)).fix_request.$post({
        body: {
          title,
          description,
          document_versions: documentVersions,
        }
      });

      // 成功時にトースト通知を表示
      setToast({ message: '修正リクエストを送信しました', type: 'success' });

      // 少し待ってからアクティビティページに遷移
      setTimeout(() => {
        window.location.href = `/change-suggestions/${id}`;
      }, 1500);
    } catch (err) {
      console.error('修正リクエスト送信エラー:', err);
      setError('修正リクエストの送信に失敗しました');
    } finally {
      setIsSubmitting(false);
    }
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

  // データ読み込み中
  if (loading) {
    return (
      <AdminLayout title="修正リクエスト作成">
        <div className="flex flex-col items-center justify-center h-full">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
          <p className="text-gray-400">データを読み込み中...</p>
        </div>
      </AdminLayout>
    );
  }

  // エラー表示
  if (error) {
    return (
      <AdminLayout title="エラー">
        <div className="flex flex-col items-center justify-center h-full">
          <div className="mb-4 p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
            <div className="flex items-center">
              <svg
                className="w-5 h-5 mr-2 text-red-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              <span>{error}</span>
            </div>
          </div>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout title="修正リクエスト作成">
      {/* トースト通知 */}
      {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}

      <div className="max-w-6xl mx-auto p-6">
        {/* ステータスバナー */}
        {pullRequestData && (
          <StatusBanner
            status={pullRequestData.status}
            authorNickname={pullRequestData.author_nickname || ''}
            createdAt={pullRequestData.created_at}
            conflict={false}
            title={pullRequestData.title}
          />
        )}

        {/* カテゴリの変更 */}
        {documentCategories.length > 0 && (
          <div className="mb-8">
            <h2 className="text-lg font-bold text-white mb-4">
              カテゴリの変更 × {documentCategories.length}
            </h2>
            {documentCategories.map((category, index) => (
              <div
                key={category.id}
                className="bg-gray-900 rounded-lg border border-gray-700 p-6 mb-4"
              >
                <div className="grid grid-cols-1 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">Slug</label>
                    <input
                      type="text"
                      value={category.slug}
                      onChange={e => handleCategoryChange(index, 'slug', e.target.value)}
                      className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">
                      カテゴリ名
                    </label>
                    <input
                      type="text"
                      value={category.sidebar_label}
                      onChange={e => handleCategoryChange(index, 'sidebar_label', e.target.value)}
                      className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">表示順</label>
                    <input
                      type="number"
                      value={category.position || ''}
                      onChange={e =>
                        handleCategoryChange(index, 'position', parseInt(e.target.value) || null)
                      }
                      className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">説明</label>
                    <textarea
                      value={category.description || ''}
                      onChange={e => handleCategoryChange(index, 'description', e.target.value)}
                      rows={3}
                      className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* ドキュメントの変更 */}
        {documentVersions.length > 0 && (
          <div className="mb-8">
            <h2 className="text-lg font-bold text-white mb-4">
              ドキュメントの変更 × {documentVersions.length}
            </h2>
            {documentVersions.map((document, index) => (
              <div
                key={document.id}
                className="bg-gray-900 rounded-lg border border-gray-700 p-6 mb-4"
              >
                <div className="grid grid-cols-1 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">Slug</label>
                    <input
                      type="text"
                      value={document.slug}
                      onChange={e => handleDocumentChange(index, 'slug', e.target.value)}
                      className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">タイトル</label>
                    <input
                      type="text"
                      value={document.sidebar_label}
                      onChange={e => handleDocumentChange(index, 'sidebar_label', e.target.value)}
                      className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">公開設定</label>
                    <select
                      value={document.status === 'published' ? '公開する' : '公開しない'}
                      onChange={e =>
                        handleDocumentChange(
                          index,
                          'status',
                          e.target.value === '公開する' ? 'published' : 'draft'
                        )
                      }
                      className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-md text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                      <option value="公開する">公開する</option>
                      <option value="公開しない">公開しない</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-300 mb-2">本文</label>
                    <div className="border border-gray-600 rounded-md overflow-hidden">
                      <SlateEditor
                        initialContent={document.content || ''}
                        onChange={(content: string) =>
                          handleDocumentChange(index, 'content', content)
                        }
                      />
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* 送信ボタン */}
        <div className="flex justify-center">
          <button
            onClick={handleSendFixRequest}
            disabled={isSubmitting}
            className="px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isSubmitting ? '送信中...' : '修正リクエストを送信'}
          </button>
        </div>
      </div>
    </AdminLayout>
  );
}
