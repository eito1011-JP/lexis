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

type DocumentEditItem = {
  original_version_id: number;
  current_version_id: number;
  is_deleted: number;
  original_document_version: DocumentVersion | null;
  current_document_version: DocumentVersion | null;
};

type CategoryEditItem = {
  original_version_id: number;
  current_version_id: number;
  is_deleted: number;
  original_category_version: CategoryVersion | null;
  current_category_version: CategoryVersion | null;
};

type EditSessionResponse = {
  documents: DocumentEditItem[];
  categories: CategoryEditItem[];
};

// SmartDiffValueコンポーネント
const SmartDiffValue: React.FC<{
  label: string;
  originalValue: any;
  currentValue: any;
  isMarkdown?: boolean;
  isDeleted?: boolean;
}> = ({ label, originalValue, currentValue, isMarkdown = false, isDeleted = false }) => {
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

  // 差分ハイライト用の関数
  const generateSplitDiffContent = (
    originalText: string,
    currentText: string,
    isMarkdown: boolean,
    isDeleted: boolean = false
  ) => {
    const originalStr = renderValue(originalText);
    const currentStr = renderValue(currentText);

    // 削除された場合は右側を空白にする
    if (isDeleted) {
      return {
        leftContent: isMarkdown ? renderMarkdownContent(originalStr) : originalStr,
        rightContent: '',
        hasChanges: true,
      };
    }

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
    originalValue,
    currentValue,
    isMarkdown,
    isDeleted
  );

  return (
    <div className="mb-4">
      <label className="block text-sm font-medium text-gray-300 mb-2">{label}</label>

      <div className="grid grid-cols-2 gap-4">
        {/* 変更前 */}
        <div className="flex">
          <div
            className={`border border-gray-800 rounded-md p-3 text-sm bg-gray-800 w-full min-h-[2.75rem] flex items-start
            }`}
          >
            <div className="flex-1">
              {typeof leftContent === 'string' ? leftContent : leftContent}
            </div>
          </div>
        </div>

        {/* 変更後 */}
        <div className="flex">
          <div
            className={`border border-gray-800 rounded-md p-3 text-sm bg-gray-800 w-full min-h-[2.75rem] flex items-start`}
          >
            <div className="flex-1">
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

  // is_deletedの値をチェックするヘルパー関数
  const isDeleted = (value: boolean | number | undefined): boolean => {
    return value === true || value === 1;
  };

  // 編集種別を判定する関数（フロント分岐表に基づく）
  const getDocumentEditType = (item: DocumentEditItem): 'create' | 'delete' | 'update' => {
    // 新規作成: original_version_id = current_version_id かつ is_deleted = 0
    if (item.original_version_id === item.current_version_id && item.is_deleted === 0) {
      return 'create';
    }
    // 削除: original_version_id ≠ current_version_id かつ current_document_version.is_deleted = 1
    if (item.original_version_id !== item.current_version_id && 
        isDeleted(item.current_document_version?.is_deleted)) {
      return 'delete';
    }
    // 変更: original_version_id ≠ current_version_id かつ is_deleted = 0
    return 'update';
  };

  const getCategoryEditType = (item: CategoryEditItem): 'create' | 'delete' | 'update' => {
    // 新規作成: original_version_id = current_version_id かつ is_deleted = 0
    if (item.original_version_id === item.current_version_id && item.is_deleted === 0) {
      return 'create';
    }
    // 削除: original_version_id ≠ current_version_id かつ current_category_version.is_deleted = 1
    if (item.original_version_id !== item.current_version_id && 
        isDeleted(item.current_category_version?.is_deleted)) {
      return 'delete';
    }
    // 変更: original_version_id ≠ current_version_id かつ is_deleted = 0
    return 'update';
  };

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
            {diffData.categories.map((categoryEdit, index) => {
              const editType = getCategoryEditType(categoryEdit);
              const originalCategory = categoryEdit.original_category_version;
              const currentCategory = categoryEdit.current_category_version;

              // フロント分岐表に基づく表示制御
              const leftValue = editType === 'create' ? null : originalCategory;
              const rightValue = editType === 'delete' ? null : currentCategory;

              return (
                <div
                  key={`category-${categoryEdit.current_version_id}-${index}`}
                  className="bg-gray-900/50 rounded-lg border border-gray-700 p-6 mb-6"
                >
                  <SlugBreadcrumb slug={currentCategory?.slug || originalCategory?.slug || ''} />

                  <SmartDiffValue
                    label="Slug"
                    originalValue={leftValue?.slug}
                    currentValue={rightValue?.slug}
                    isDeleted={editType === 'delete'}
                  />

                  <SmartDiffValue
                    label="カテゴリ名"
                    originalValue={leftValue?.sidebar_label}
                    currentValue={rightValue?.sidebar_label}
                    isDeleted={editType === 'delete'}
                  />

                  <SmartDiffValue
                    label="表示順"
                    originalValue={leftValue?.position}
                    currentValue={rightValue?.position}
                    isDeleted={editType === 'delete'}
                  />

                  <SmartDiffValue
                    label="説明"
                    originalValue={leftValue?.description}
                    currentValue={rightValue?.description}
                    isDeleted={editType === 'delete'}
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
            {diffData.documents.map((documentEdit, index) => {
              const editType = getDocumentEditType(documentEdit);
              const originalDoc = documentEdit.original_document_version;
              const currentDoc = documentEdit.current_document_version;

              // フロント分岐表に基づく表示制御
              const leftValue = editType === 'create' ? null : originalDoc;
              const rightValue = editType === 'delete' ? null : currentDoc;

              return (
                <div
                  key={`document-${documentEdit.current_version_id}-${index}`}
                  className="bg-gray-900/50 rounded-lg border border-gray-700 p-6 mb-6"
                >
                  <SlugBreadcrumb slug={currentDoc?.slug || originalDoc?.slug || ''} />

                  <SmartDiffValue
                    label="Slug"
                    originalValue={leftValue?.slug}
                    currentValue={rightValue?.slug}
                    isDeleted={editType === 'delete'}
                  />

                  <SmartDiffValue
                    label="タイトル"
                    originalValue={leftValue?.sidebar_label}
                    currentValue={rightValue?.sidebar_label}
                    isDeleted={editType === 'delete'}
                  />

                  <SmartDiffValue
                    label="表示順序"
                    originalValue={leftValue?.file_order}
                    currentValue={rightValue?.file_order}
                    isDeleted={editType === 'delete'}
                  />

                  <SmartDiffValue
                    label="公開設定"
                    originalValue={leftValue?.is_public}
                    currentValue={rightValue?.is_public}
                    isDeleted={editType === 'delete'}
                  />

                  <SmartDiffValue
                    label="本文"
                    originalValue={leftValue?.content}
                    currentValue={rightValue?.content}
                    isMarkdown={true}
                    isDeleted={editType === 'delete'}
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
