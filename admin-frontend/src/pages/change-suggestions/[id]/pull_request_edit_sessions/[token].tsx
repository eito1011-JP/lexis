import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { Toast } from '@/components/admin/Toast';
import { markdownToHtml } from '@/utils/markdownToHtml';
import { markdownStyles } from '@/styles/markdownContent';
import { diffStyles } from '@/styles/diffStyles';
import { makeDiff, cleanupSemantic } from '@sanity/diff-match-patch';

// 新しい仕様に基づく型定義
type DocumentVersion = {
  id: number;
  slug: string;
  sidebar_label: string;
  description?: string;
  title?: string;
  content?: string;
  is_public?: boolean | number;
  position?: number;
  file_order?: number;
  parent_id?: number;
  category_id?: number;
  status: string;
  user_branch_id: number;
  created_at: string;
  updated_at: string;
  is_deleted?: boolean | number;
  deleted_at?: string | null;
};

type CategoryVersion = {
  id: number;
  slug: string;
  sidebar_label: string;
  description?: string;
  position?: number;
  parent_id?: number;
  user_branch_id: number;
  created_at: string;
  updated_at: string;
  is_deleted?: boolean | number;
  deleted_at?: string | null;
};

// 新しいAPI仕様に基づく型定義
type DocumentDiff = {
  diff_type: 'created' | 'deleted' | 'updated';
  original: DocumentVersion | null;
  current: DocumentVersion | null;
};

type CategoryDiff = {
  diff_type: 'created' | 'deleted' | 'updated';
  original: CategoryVersion | null;
  current: CategoryVersion | null;
};

type EditSessionResponse = {
  documents: DocumentDiff[];
  categories: CategoryDiff[];
};

// SmartDiffValueコンポーネント
const SmartDiffValue: React.FC<{
  label: string;
  originalValue: any;
  currentValue: any;
  isMarkdown?: boolean;
  diffType: 'created' | 'deleted' | 'updated';
}> = ({ label, originalValue, currentValue, isMarkdown = false, diffType }) => {
  const renderValue = (value: any) => {
    if (value === null || value === undefined) return '';
    if (typeof value === 'boolean') return value ? '公開' : '非公開';
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

  // diff_typeに基づく表示コンテンツの生成
  const generateDiffContent = () => {
    const originalStr = renderValue(originalValue);
    const currentStr = renderValue(currentValue);

    switch (diffType) {
      case 'created': {
        // currentのみ表示、diff.tsxのスタイルに合わせる
        if (isMarkdown && currentStr) {
          try {
            const currentHtml = markdownToHtml(currentStr);
            const wrappedHtml = wrapWithDiffClass(currentHtml, 1); // 1 = 追加

            return {
              leftContent: '',
              rightContent: (
                <div
                  className="markdown-content prose prose-invert max-w-none"
                  dangerouslySetInnerHTML={{ __html: wrappedHtml }}
                />
              ),
            };
          } catch (error) {
            console.warn('マークダウン新規追加表示エラー:', error);
          }
        }

        // プレーンテキストの新規追加処理
        if (currentStr) {
          const escapedText = currentStr
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br/>');

          return {
            leftContent: '',
            rightContent: (
              <span
                dangerouslySetInnerHTML={{
                  __html: `<span class="diff-added-content">${escapedText}</span>`,
                }}
              />
            ),
          };
        }

        return {
          leftContent: '',
          rightContent: '',
        };
      }

      case 'deleted': {
        // originalのみ表示、diff.tsxのスタイルに合わせる
        if (isMarkdown && originalStr) {
          try {
            const originalHtml = markdownToHtml(originalStr);
            const wrappedHtml = wrapWithDiffClass(originalHtml, -1); // -1 = 削除

            return {
              leftContent: (
                <div
                  className="markdown-content prose prose-invert max-w-none"
                  dangerouslySetInnerHTML={{ __html: wrappedHtml }}
                />
              ),
              rightContent: '',
            };
          } catch (error) {
            console.warn('マークダウン削除表示エラー:', error);
          }
        }

        // プレーンテキストの削除処理
        if (originalStr) {
          const escapedText = originalStr
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br/>');

          return {
            leftContent: (
              <span
                dangerouslySetInnerHTML={{
                  __html: `<span class="diff-deleted-content">${escapedText}</span>`,
                }}
              />
            ),
            rightContent: '',
          };
        }

        return {
          leftContent: '',
          rightContent: '',
        };
      }

      case 'updated': {
        if (originalStr === currentStr) {
          // 変更がない場合は通常表示
          const content = isMarkdown ? renderMarkdownContent(originalStr) : originalStr;
          return {
            leftContent: content,
            rightContent: content,
          };
        }

        // マークダウンの場合の差分処理
        if (isMarkdown) {
          try {
            const originalHtml = markdownToHtml(originalStr);
            const currentHtml = markdownToHtml(currentStr);
            const diffs = makeDiff(originalHtml, currentHtml);
            const cleanedDiffs = cleanupSemantic(diffs);

            let leftHtml = '';
            let rightHtml = '';

            for (const [operation, text] of cleanedDiffs) {
              switch (operation) {
                case -1: // 削除（左側でハイライト）
                  leftHtml += wrapWithDiffClass(text, -1);
                  break;
                case 1: // 追加（右側でハイライト）
                  rightHtml += wrapWithDiffClass(text, 1);
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
            };
          } catch (error) {
            console.warn('マークダウン差分表示エラー:', error);
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
              break;
            case 1: // 追加（右側に表示）
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
        };
      }

      default:
        return {
          leftContent: '',
          rightContent: '',
        };
    }
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

  const { leftContent, rightContent } = generateDiffContent();

  return (
    <div className="mb-4">
      <label className="block text-sm font-medium text-gray-300 mb-2">{label}</label>

      <div className="grid grid-cols-2 gap-4">
        {/* 変更前 */}
        <div className="flex">
          <div className="border border-gray-800 rounded-md p-3 text-sm bg-gray-800 w-full min-h-[2.75rem] flex items-start">
            <div className="w-full">
              {typeof leftContent === 'string' ? leftContent : leftContent}
            </div>
          </div>
        </div>

        {/* 変更後 */}
        <div className="flex">
          <div className="border border-gray-800 rounded-md p-3 text-sm bg-gray-800 w-full min-h-[2.75rem] flex items-start">
            <div className="w-full">
              {typeof rightContent === 'string' ? rightContent : rightContent}
            </div>
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

export default function PullRequestEditSessionDetailPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/login', false);
  const { token } = useParams<{ token: string }>();

  const [diffData, setDiffData] = useState<EditSessionResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' } | null>(null);

  useEffect(() => {
    const fetchEditDiff = async () => {
      if (!token) {
        setError('トークンが指定されていません');
        setLoading(false);
        return;
      }

      try {
        setLoading(true);
        const response = await apiClient.get(
          `${API_CONFIG.ENDPOINTS.PULL_REQUEST_EDIT_SESSIONS.GET}?token=${token}`
        );
        console.log('response', response);
        setDiffData(response);
      } catch (err: any) {
        console.error('編集差分取得エラー:', err);
        setError('編集差分の取得に失敗しました');
        setToast({
          message: '編集差分の取得に失敗しました',
          type: 'error',
        });
      } finally {
        setLoading(false);
      }
    };

    fetchEditDiff();
  }, [token]);

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
      <AdminLayout title="変更提案編集詳細">
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
      <AdminLayout title="変更提案編集詳細">
        <div className="flex flex-col items-center justify-center h-full">
          <p className="text-gray-400">データが見つかりません</p>
        </div>
      </AdminLayout>
    );
  }

  console.log('Fetched data:', {
    documents: diffData.documents,
    categories: diffData.categories,
  });

  return (
    <AdminLayout title="変更提案編集詳細">
      <style>{markdownStyles}</style>
      <style>{diffStyles}</style>
      {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}

      <div className="mb-20 w-full rounded-lg relative">
        {/* ヘッダー */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-white mb-4">変更提案編集詳細</h1>
          <div className="text-gray-400">
            この変更提案の編集内容を確認できます。(変更前 / 変更後)
          </div>
        </div>

        {/* カテゴリの変更 */}
        {diffData.categories.length > 0 && (
          <div className="mb-8">
            <h2 className="text-xl font-bold text-white mb-6">
              📁 カテゴリの変更 × {diffData.categories.length}
            </h2>
            {diffData.categories.map((categoryDiff, index) => {
              const { original, current, diff_type } = categoryDiff;
              const slug = current?.slug || original?.slug || '';

              return (
                <div
                  key={`category-${index}`}
                  className="bg-gray-900/50 rounded-lg border border-gray-700 p-6 mb-6"
                >
                  <SlugBreadcrumb slug={slug} />

                  <SmartDiffValue
                    label="Slug"
                    originalValue={original?.slug}
                    currentValue={current?.slug}
                    diffType={diff_type}
                  />

                  <SmartDiffValue
                    label="カテゴリ名"
                    originalValue={original?.sidebar_label}
                    currentValue={current?.sidebar_label}
                    diffType={diff_type}
                  />

                  <SmartDiffValue
                    label="表示順"
                    originalValue={original?.position}
                    currentValue={current?.position}
                    diffType={diff_type}
                  />

                  <SmartDiffValue
                    label="説明"
                    originalValue={original?.description}
                    currentValue={current?.description}
                    diffType={diff_type}
                  />
                </div>
              );
            })}
          </div>
        )}

        {/* ドキュメントの変更 */}
        {diffData.documents.length > 0 && (
          <div className="mb-8">
            <h2 className="text-xl font-bold text-white mb-6">
              📝 ドキュメントの変更 × {diffData.documents.length}
            </h2>
            {diffData.documents.map((documentDiff, index) => {
              const { original, current, diff_type } = documentDiff;
              const slug = current?.slug || original?.slug || '';

              return (
                <div
                  key={`document-${index}`}
                  className="bg-gray-900/50 rounded-lg border border-gray-700 p-6 mb-6"
                >
                  <SlugBreadcrumb slug={slug} />

                  <SmartDiffValue
                    label="Slug"
                    originalValue={original?.slug}
                    currentValue={current?.slug}
                    diffType={diff_type}
                  />

                  <SmartDiffValue
                    label="タイトル"
                    originalValue={original?.sidebar_label}
                    currentValue={current?.sidebar_label}
                    diffType={diff_type}
                  />

                  <SmartDiffValue
                    label="表示順序"
                    originalValue={original?.file_order}
                    currentValue={current?.file_order}
                    diffType={diff_type}
                  />

                  <SmartDiffValue
                    label="公開設定"
                    originalValue={original?.is_public}
                    currentValue={current?.is_public}
                    diffType={diff_type}
                  />

                  <SmartDiffValue
                    label="本文"
                    originalValue={original?.content}
                    currentValue={current?.content}
                    isMarkdown={true}
                    diffType={diff_type}
                  />
                </div>
              );
            })}
          </div>
        )}

        {/* データが空の場合 */}
        {diffData.categories.length === 0 && diffData.documents.length === 0 && (
          <div className="text-center py-12">
            <div className="text-gray-400 text-lg">変更内容がありません</div>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
