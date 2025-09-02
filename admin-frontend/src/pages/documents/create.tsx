import { useState, useEffect } from 'react';
import AdminLayout from '@/components/admin/layout';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import SlateEditor from '@/components/admin/editor/SlateEditor';
import { markdownStyles } from '@/styles/markdownContent';

// ユーザー型定義を追加
interface User {
  id: string;
  email: string;
}

// エラー型の定義を追加
interface ApiError {
  message?: string;
}

export default function CreateDocumentPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/login', false);

  const [label, setLabel] = useState('');
  const [content, setContent] = useState('');
  const [publicOption, setPublicOption] = useState('公開する');
  const [slug, setSlug] = useState('');
  const [fileOrder, setFileOrder] = useState('');
  const [invalidSlug, setInvalidSlug] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

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
    setSlug(value);
    validateSlug(value);
  };

  const handleEditorChange = (markdown: string) => {
    setContent(markdown);
  };

  const handleSave = async () => {
    if (isSubmitting) return; // 既に送信中なら何もしない

    try {
      setIsSubmitting(true);

      if (!label) {
        alert('タイトルを入力してください');
        return;
      }

      const queryParams = new URLSearchParams(window.location.search);
      const category_path = queryParams.get('category_path');
      const editPullRequestId = queryParams.get('edit_pull_request_id');

      // APIリクエストのペイロードを構築
      const payload: any = {
        category_path: category_path,
        sidebar_label: label,
        content: content,
        is_public: publicOption === '公開する', // 公開設定を真偽値に変換
        slug,
        file_order: fileOrder,
      };
      const pullRequestEditToken = localStorage.getItem('pullRequestEditToken');
      if (editPullRequestId && pullRequestEditToken) {
        payload.edit_pull_request_id = editPullRequestId;
        payload.pull_request_edit_token = pullRequestEditToken;
      }

      // ドキュメント作成APIを呼び出す
      await apiClient.post(`${API_CONFIG.ENDPOINTS.DOCUMENTS.CREATE}`, payload);

      alert('ドキュメントが作成されました');
      // 成功したら一覧ページに戻る
      let redirectUrl = `/documents/${category_path ?? ''}`;
      if (editPullRequestId && pullRequestEditToken) {
        redirectUrl += `?edit_pull_request_id=${editPullRequestId}&pull_request_edit_token=${pullRequestEditToken}`;
      }
      window.location.href = redirectUrl;
    } catch (error: unknown) {
      console.error('ドキュメント作成エラー:', error);
      const apiError = error as ApiError;
      alert(`ドキュメントの作成に失敗しました: ${apiError.message || '不明なエラー'}`);
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

  return (
    <AdminLayout title="ドキュメント作成">
      <style>{markdownStyles}</style>
      <div className="max-w-4xl mx-auto">
        <div className="mb-6">
          <h1 className="text-2xl font-bold mb-4">ドキュメント作成</h1>
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
          <label className="block mb-2 font-bold">タイトル</label>
            <input
              type="text"
              value={label}
              onChange={e => setLabel(e.target.value)}
              className="w-full p-2.5 border border-gray-700 rounded bg-transparent text-white"
              placeholder="タイトルを入力してください"
            />
          </div>

          <div className="gap-6 mt-8">
            <div>
              <label className="block mb-2 font-bold">本文</label>
              <div className="w-full p-2.5 border border-gray-700 rounded bg-black text-white min-h-72">
                <SlateEditor
                  initialContent=""
                  onChange={() => {}}
                  onMarkdownChange={handleEditorChange}
                  placeholder="ここにドキュメントを作成してください"
                />
              </div>
            </div>
          </div>

          <div className="flex flex-col items-center gap-4 mt-8">
          <div className="flex gap-4">
            <button
              className="px-4 py-2 bg-[#b1b1b1] text-white rounded hover:bg-opacity-80 border-none w-45 disabled:opacity-50 disabled:cursor-not-allowed"
              onClick={handleSave}
              disabled={!!invalidSlug || isSubmitting}
            >
              プレビュー
            </button>
            <button
              className="px-4 py-2 bg-[#3832A5] text-white rounded hover:bg-opacity-80 border-none w-45 disabled:opacity-50 disabled:cursor-not-allowed"
              onClick={handleSave}
              disabled={!!invalidSlug || isSubmitting}
            >
              {isSubmitting ? '保存中...' : '保存'}
            </button>
          </div>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
}
