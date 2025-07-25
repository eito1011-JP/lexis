import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useParams, useLocation, useNavigate } from 'react-router-dom';
import { Toast } from '@/components/admin/Toast';
import { markdownToHtml } from '@/utils/markdownToHtml';
import { markdownStyles } from '@/styles/markdownContent';
import { diffStyles } from '@/styles/diffStyles';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { makeDiff, cleanupSemantic } from '@sanity/diff-match-patch';

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
  parent_id?: number;
  category_id?: number;
  status: string;
  user_branch_id: number;
  base_document_version_id?: number;
  base_category_version_id?: number;
  created_at: string;
  updated_at: string;
};

// API レスポンスの型定義
type FixRequestDiffResponse = {
  current_pr: {
    documents: DiffItem[];
    categories: DiffItem[];
  };
  fix_request: {
    documents: DiffItem[];
    categories: DiffItem[];
  };
};

// SmartDiffValueコンポーネント
const SmartDiffValue: React.FC<{
  label: string;
  currentValue: any;
  fixRequestValue: any;
  isMarkdown?: boolean;
}> = ({ label, currentValue, fixRequestValue, isMarkdown = false }) => {
  const renderValue = (value: any) => {
    if (value === null || value === undefined) return '(なし)';
    if (typeof value === 'boolean') return value ? 'はい' : 'いいえ';
    return String(value);
  };

  // ブロック要素を検出する関数
  const isBlockElement = (html: string): boolean => {
    const blockElementPattern = /^<(h[1-6]|p|div|section|article|blockquote|pre|ul|ol|li)(\s|>)/i;
    return blockElementPattern.test(html.trim());
  };

  // HTMLテキストを適切なクラスでラップする関数
  const wrapWithDiffClass = (html: string, operation: number): string => {
    if (operation === 0) return html; // 変更なしの場合はそのまま

    const isBlock = isBlockElement(html);
    const className =
      operation === 1
        ? isBlock
          ? 'diff-block-added'
          : 'diff-added-content'
        : isBlock
          ? 'diff-block-deleted'
          : 'diff-deleted-content';

    const wrapper = isBlock ? 'div' : 'span';
    return `<${wrapper} class="${className}">${html}</${wrapper}>`;
  };

  // 差分ハイライト用の関数
  const generateSplitDiffContent = (
    originalText: string,
    currentText: string,
    isMarkdown: boolean
  ) => {
    const originalStr = renderValue(originalText);
    const currentStr = renderValue(currentText);

    if (originalStr === currentStr) {
      // 変更がない場合は通常表示
      return {
        leftContent: isMarkdown ? renderMarkdownContent(originalStr) : originalStr,
        rightContent: isMarkdown ? renderMarkdownContent(currentStr) : currentStr,
        hasChanges: false,
      };
    }

    // マークダウンの場合の処理
    if (isMarkdown) {
      try {
        // まず両方のマークダウンをHTMLに変換
        const originalHtml = markdownToHtml(originalStr);
        const currentHtml = markdownToHtml(currentStr);

        // HTMLベースで差分を計算
        const diffs = makeDiff(originalHtml, currentHtml);
        const cleanedDiffs = cleanupSemantic(diffs);

        // 左側用と右側用のHTMLを生成
        let leftHtml = '';
        let rightHtml = '';

        for (const [operation, text] of cleanedDiffs) {
          switch (operation) {
            case -1: // 削除（左側でハイライト）
              leftHtml += wrapWithDiffClass(text, -1);
              // 右側には追加しない
              break;
            case 1: // 追加（右側でハイライト）
              rightHtml += wrapWithDiffClass(text, 1);
              // 左側には追加しない
              break;
            case 0: // 変更なし（両側に追加）
              leftHtml += text;
              rightHtml += text;
              break;
          }
        }

        return {
          leftContent: (
            <div
              className="markdown-content prose prose-invert max-w-none"
              dangerouslySetInnerHTML={{ __html: leftHtml }}
            />
          ),
          rightContent: (
            <div
              className="markdown-content prose prose-invert max-w-none"
              dangerouslySetInnerHTML={{ __html: rightHtml }}
            />
          ),
          hasChanges: true,
        };
      } catch (error) {
        console.warn('マークダウン差分表示エラー:', error);
        // エラーの場合はプレーンテキストで処理
      }
    }

    // プレーンテキストの差分処理
    const diffs = makeDiff(originalStr, currentStr);
    const cleanedDiffs = cleanupSemantic(diffs);

    let leftHtml = '';
    let rightHtml = '';

    for (const [operation, text] of cleanedDiffs) {
      const escapedText = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\n/g, '<br/>');

      switch (operation) {
        case -1: // 削除（左側に表示）
          leftHtml += `<span class="diff-deleted-content">${escapedText}</span>`;
          rightHtml += ''; // 右側には表示しない
          break;
        case 1: // 追加（右側に表示）
          leftHtml += ''; // 左側には表示しない
          rightHtml += `<span class="diff-added-content">${escapedText}</span>`;
          break;
        case 0: // 変更なし（両側に表示）
          leftHtml += escapedText;
          rightHtml += escapedText;
          break;
      }
    }

    return {
      leftContent: <span dangerouslySetInnerHTML={{ __html: leftHtml }} />,
      rightContent: <span dangerouslySetInnerHTML={{ __html: rightHtml }} />,
      hasChanges: true,
    };
  };

  const renderMarkdownContent = (content: string) => {
    if (!isMarkdown || !content) return content;

    try {
      const htmlContent = markdownToHtml(content);
      return (
        <div
          className="markdown-content prose prose-invert max-w-none"
          dangerouslySetInnerHTML={{ __html: htmlContent }}
        />
      );
    } catch (error) {
      return content;
    }
  };

  const { leftContent, rightContent } = generateSplitDiffContent(
    currentValue,
    fixRequestValue,
    isMarkdown
  );

  return (
    <div className="mb-4">
      <label className="block text-sm font-medium text-gray-300 mb-2">{label}</label>

      <div className="grid grid-cols-2 gap-4">
        {/* 現在の変更提案 */}
        <div>
          <div
            className={`border border-gray-800 rounded-md p-3 text-sm bg-gray-800
            }`}
          >
            {typeof leftContent === 'string' ? leftContent : leftContent}
          </div>
        </div>

        {/* 修正リクエスト */}
        <div>
          <div className={`border border-gray-800 rounded-md p-3 text-sm bg-gray-800`}>
            {typeof rightContent === 'string' ? rightContent : rightContent}
          </div>
        </div>
      </div>
    </div>
  );
};

// SlugBreadcrumbコンポーネント
const SlugBreadcrumb: React.FC<{ slug: string }> = ({ slug }) => {
  const parts = slug.split('/').filter(Boolean);

  return (
    <div className="mb-4 text-sm text-gray-400">
      <span>/</span>
      {parts.map((part, index) => (
        <span key={index}>
          <span className="text-gray-300">{part}</span>
          {index < parts.length - 1 && <span>/</span>}
        </span>
      ))}
    </div>
  );
};

export default function FixRequestDetailPage(): JSX.Element {
  const { id } = useParams<{ id: string }>();
  const location = useLocation();
  const navigate = useNavigate();
  // クエリパラメータからtokenを取得
  const searchParams = new URLSearchParams(location.search);
  const token = searchParams.get('token');
  const [diffData, setDiffData] = useState<FixRequestDiffResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [applying, setApplying] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  // 修正リクエスト差分データ取得
  const fetchFixRequestDiff = async () => {
    if (!id) {
      setError('プルリクエストIDが指定されていません');
      setLoading(false);
      return;
    }

    if (!token) {
      setError('トークンが指定されていません');
      setLoading(false);
      return;
    }

    try {
      setLoading(true);
      // apiClientのgetを利用し、tokenをクエリパラメータとして渡す
      const response = await apiClient.get(
        `${API_CONFIG.ENDPOINTS.FIX_REQUESTS.GET_DIFF.replace(':token', token)}`,
        {
          params: { pull_request_id: id },
        }
      );
      console.log('response', response);
      setDiffData(response);
    } catch (err) {
      console.error('修正リクエスト差分取得エラー:', err);
      setError('修正リクエスト差分の取得に失敗しました');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchFixRequestDiff();
  }, [id, token]);

  // 修正リクエスト適用処理
  const handleApplyFixRequest = async () => {
    if (!id || !token) {
      setToast({ message: '必要なパラメータが不足しています', type: 'error' });
      return;
    }

    try {
      setApplying(true);
      await apiClient.post(`/api/admin/fix-requests/apply`, {
        token: token,
      });
      setToast({ message: '修正リクエストが正常に適用されました', type: 'success' });

      navigate(`/change-suggestions/${id}`);
    } catch (err: any) {
      console.error('修正リクエスト適用エラー:', err);
      setToast({ message: '修正リクエストの適用に失敗しました', type: 'error' });
    } finally {
      setApplying(false);
    }
  };

  // データ読み込み中
  if (loading) {
    return (
      <AdminLayout title="修正リクエスト詳細">
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

  if (!diffData) {
    return (
      <AdminLayout title="修正リクエスト詳細">
        <div className="flex flex-col items-center justify-center h-full">
          <p className="text-gray-400">データが見つかりません</p>
        </div>
      </AdminLayout>
    );
  }

  // base_document_version_id を使って現在の文書と修正リクエストの文書をペアリング
  const documentPairs: Array<{
    current: DiffItem | null;
    fixRequest: DiffItem;
  }> = [];

  // fix_request の文書を基準にペアを作成
  diffData.fix_request.documents.forEach(fixRequestDoc => {
    const currentDoc = diffData.current_pr.documents.find(
      doc => doc.id === fixRequestDoc.base_document_version_id
    );
    documentPairs.push({
      current: currentDoc || null,
      fixRequest: fixRequestDoc,
    });
  });

  // base_category_version_id を使って現在のカテゴリと修正リクエストのカテゴリをペアリング
  const categoryPairs: Array<{
    current: DiffItem | null;
    fixRequest: DiffItem;
  }> = [];

  // fix_request のカテゴリを基準にペアを作成
  diffData.fix_request.categories.forEach(fixRequestCat => {
    const currentCat = diffData.current_pr.categories.find(
      cat => cat.id === fixRequestCat.base_category_version_id
    );
    categoryPairs.push({
      current: currentCat || null,
      fixRequest: fixRequestCat,
    });
  });

  return (
    <AdminLayout title="修正リクエスト詳細">
      <style>{markdownStyles}</style>
      <style>{diffStyles}</style>
      {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}

      <div className="mb-20 w-full rounded-lg relative">
        {/* ヘッダー */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-white mb-4">修正リクエスト詳細</h1>
          <div className="text-gray-400">
            変更提案 #{id} に対する修正リクエストの内容確認 (現在の変更提案 / 修正リクエスト)
          </div>
        </div>

        {/* カテゴリの変更 */}
        {categoryPairs.length > 0 && (
          <div className="mb-8">
            <h2 className="text-xl font-bold text-white mb-6">
              📁 カテゴリの変更 × {categoryPairs.length}
            </h2>
            {categoryPairs.map((pair, index) => {
              return (
                <div
                  key={`category-${pair.fixRequest.id}-${index}`}
                  className="bg-gray-900/50 rounded-lg border border-gray-700 p-6 mb-6"
                >
                  <SlugBreadcrumb slug={pair.fixRequest.slug} />

                  <SmartDiffValue
                    label="カテゴリ名"
                    currentValue={pair.current?.sidebar_label}
                    fixRequestValue={pair.fixRequest.sidebar_label}
                  />

                  <SmartDiffValue
                    label="表示順"
                    currentValue={pair.current?.position}
                    fixRequestValue={pair.fixRequest.position}
                  />

                  <SmartDiffValue
                    label="説明"
                    currentValue={pair.current?.description}
                    fixRequestValue={pair.fixRequest.description}
                  />
                </div>
              );
            })}
          </div>
        )}

        {/* ドキュメントの変更 */}
        {documentPairs.length > 0 && (
          <div className="mb-8">
            <h2 className="text-xl font-bold text-white mb-6">
              📝 ドキュメントの変更 × {documentPairs.length}
            </h2>
            {documentPairs.map((pair, index) => {
              return (
                <div
                  key={`document-${pair.fixRequest.id}-${index}`}
                  className="bg-gray-900/50 rounded-lg border border-gray-700 p-6 mb-6"
                >
                  <SmartDiffValue
                    label="Slug"
                    currentValue={pair.current?.slug}
                    fixRequestValue={pair.fixRequest.slug}
                  />

                  <SmartDiffValue
                    label="タイトル"
                    currentValue={pair.current?.sidebar_label}
                    fixRequestValue={pair.fixRequest.sidebar_label}
                  />

                  <SmartDiffValue
                    label="公開設定"
                    currentValue={pair.current?.status === 'published' ? '公開する' : '公開しない'}
                    fixRequestValue={
                      pair.fixRequest.status === 'published' ? '公開する' : '公開しない'
                    }
                  />

                  <SmartDiffValue
                    label="本文"
                    currentValue={pair.current?.content}
                    fixRequestValue={pair.fixRequest.content}
                    isMarkdown={true}
                  />
                </div>
              );
            })}
          </div>
        )}

        {/* データが空の場合 */}
        {categoryPairs.length === 0 && documentPairs.length === 0 && (
          <div className="text-center py-12">
            <div className="text-gray-400 text-lg">修正リクエストのデータがありません</div>
          </div>
        )}

        {/* 修正リクエスト適用ボタン */}
        <div className="flex justify-center mt-8 mb-20">
          <button
            onClick={handleApplyFixRequest}
            disabled={loading || applying}
            className="bg-[#3832A5] hover:bg-[#3832A5] disabled:bg-gray-600 disabled:cursor-not-allowed text-white font-bold py-3 px-8 rounded-lg shadow-lg transition-all duration-200 flex items-center space-x-2"
          >
            {applying ? (
              <>
                <div className="animate-spin disabled:opacity-50 rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></div>
                <span>適用中...</span>
              </>
            ) : (
              <span>修正リクエストを適用</span>
            )}
          </button>
        </div>
      </div>
    </AdminLayout>
  );
}
