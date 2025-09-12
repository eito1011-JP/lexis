import AdminLayout from '@/components/admin/layout';
import { useState, useEffect, useRef } from 'react';
import type { JSX } from 'react';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { Folder } from '@/components/icon/common/Folder';
import React from 'react';
import { markdownToHtml } from '@/utils/markdownToHtml';
import { DocumentDetailed } from '@/components/icon/common/DocumentDetailed';
import { Settings } from '@/components/icon/common/Settings';
import { createPullRequest, type DiffItem as ApiDiffItem } from '@/api/pullRequest';
import { markdownStyles } from '@/styles/markdownContent';

// 差分データの型定義
type DiffItem = {
  id: number;
  slug: string;
  sidebar_label?: string; // ドキュメント用
  title?: string; // カテゴリ用
  description?: string;
  content?: string;
  file_order?: number;
  parent_id?: number;
  category_id?: number;
  category_path?: string; // カテゴリ階層パス
  status: string;
  user_branch_id: number;
  created_at: string;
  updated_at: string;
};

type DiffFieldInfo = {
  status: 'added' | 'deleted' | 'modified' | 'unchanged';
  current: any;
  original: any;
};

type DiffDataInfo = {
  id: number;
  type: 'document' | 'category';
  operation: 'created' | 'updated' | 'deleted';
  changed_fields: Record<string, DiffFieldInfo>;
};

type DiffResponse = {
  document_versions: DiffItem[];
  document_categories: DiffItem[];
  original_document_versions?: DiffItem[];
  original_document_categories?: DiffItem[];
  diff_data: DiffDataInfo[];
};

// テーブル形式のdiff表示コンポーネント
const LineDiffDisplay = ({
  oldText,
  newText,
  showLineNumbers = true,
}: {
  oldText: string;
  newText: string;
  showLineNumbers?: boolean;
}) => {
  // LCS（最長共通部分列）を使った高精度な差分計算
  const calculateLineDiff = (oldText: string, newText: string) => {
    const oldLines = oldText ? oldText.split('\n') : [];
    const newLines = newText ? newText.split('\n') : [];

    // LCSアルゴリズムで共通行を見つける
    const lcs = (a: string[], b: string[]) => {
      const dp: number[][] = Array(a.length + 1)
        .fill(null)
        .map(() => Array(b.length + 1).fill(0));

      for (let i = 1; i <= a.length; i++) {
        for (let j = 1; j <= b.length; j++) {
          if (a[i - 1] === b[j - 1]) {
            dp[i][j] = dp[i - 1][j - 1] + 1;
          } else {
            dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1]);
          }
        }
      }

      // バックトラックして共通行のインデックスを取得
      const result: Array<{ oldIndex: number; newIndex: number }> = [];
      let i = a.length,
        j = b.length;

      while (i > 0 && j > 0) {
        if (a[i - 1] === b[j - 1]) {
          result.unshift({ oldIndex: i - 1, newIndex: j - 1 });
          i--;
          j--;
        } else if (dp[i - 1][j] > dp[i][j - 1]) {
          i--;
        } else {
          j--;
        }
      }

      return result;
    };

    const commonLines = lcs(oldLines, newLines);
    const result: Array<{
      type: 'added' | 'deleted' | 'unchanged' | 'change';
      content: string;
      oldLineNo?: number;
      newLineNo?: number;
      deletedContent?: string;
      addedContent?: string;
    }> = [];

    let oldIndex = 0;
    let newIndex = 0;
    let oldLineNo = 1;
    let newLineNo = 1;
    let commonIndex = 0;

    while (oldIndex < oldLines.length || newIndex < newLines.length) {
      // 次の共通行があるかチェック
      const nextCommon = commonIndex < commonLines.length ? commonLines[commonIndex] : null;

      if (nextCommon && oldIndex === nextCommon.oldIndex && newIndex === nextCommon.newIndex) {
        // 共通行（未変更）
        result.push({
          type: 'unchanged',
          content: oldLines[oldIndex],
          oldLineNo: oldLineNo,
          newLineNo: newLineNo,
        });
        oldIndex++;
        newIndex++;
        oldLineNo++;
        newLineNo++;
        commonIndex++;
      } else if (nextCommon && oldIndex < nextCommon.oldIndex && newIndex < nextCommon.newIndex) {
        // 変更行（削除と追加が同時に発生）
        result.push({
          type: 'change',
          content: '',
          oldLineNo: oldLineNo,
          newLineNo: newLineNo,
          deletedContent: oldLines[oldIndex],
          addedContent: newLines[newIndex],
        });
        oldIndex++;
        newIndex++;
        oldLineNo++;
        newLineNo++;
      } else if (nextCommon && oldIndex < nextCommon.oldIndex) {
        // 削除された行
        result.push({
          type: 'deleted',
          content: oldLines[oldIndex],
          oldLineNo: oldLineNo,
          newLineNo: undefined,
        });
        oldIndex++;
        oldLineNo++;
      } else if (nextCommon && newIndex < nextCommon.newIndex) {
        // 追加された行
        result.push({
          type: 'added',
          content: newLines[newIndex],
          oldLineNo: undefined,
          newLineNo: newLineNo,
        });
        newIndex++;
        newLineNo++;
      } else {
        // ファイル末尾の処理
        if (oldIndex < oldLines.length) {
          result.push({
            type: 'deleted',
            content: oldLines[oldIndex],
            oldLineNo: oldLineNo,
            newLineNo: undefined,
          });
          oldIndex++;
          oldLineNo++;
        }
        if (newIndex < newLines.length) {
          result.push({
            type: 'added',
            content: newLines[newIndex],
            oldLineNo: undefined,
            newLineNo: newLineNo,
          });
          newIndex++;
          newLineNo++;
        }
      }
    }

    return result;
  };

  const diffLines = calculateLineDiff(oldText || '', newText || '');

  return (
    <div className="border border-gray-700 rounded-lg overflow-hidden bg-gray-900">
      <table className="w-full border-collapse font-mono text-sm">
        <tbody>
          {diffLines.map((line, index) => {
            const getRowClass = () => {
              switch (line.type) {
                case 'added':
                  return 'bg-green-900/50 border-green-700';
                case 'deleted':
                  return 'bg-red-900/50 border-red-700';
                case 'change':
                  return '';
                default:
                  return 'bg-gray-800/30';
              }
            };

            if (line.type === 'change') {
              // 変更行は削除と追加の2行で表示
              return (
                <React.Fragment key={index}>
                  {/* 削除行 */}
                  <tr className="bg-red-900/50 border-red-700">
                    {showLineNumbers && (
                      <>
                        <td className="px-2 py-1 text-gray-400 text-right select-none w-[35px]">
                          <div className="text-xs font-mono">{line.oldLineNo}</div>
                        </td>
                        <td className="px-2 py-1 text-gray-400 text-right select-none w-[35px]">
                          <div className="text-xs font-mono"></div>
                        </td>
                      </>
                    )}
                    <td className="px-3 py-1 text-red-300 w-[20px]">
                      <span className="font-bold">-</span>
                    </td>
                    <td className="px-3 py-1 text-white">
                      <div className="font-mono text-sm leading-relaxed">
                        <del>{line.deletedContent || ' '}</del>
                      </div>
                    </td>
                  </tr>
                  {/* 追加行 */}
                  <tr className="bg-green-900/50 border-green-700">
                    {showLineNumbers && (
                      <>
                        <td className="px-2 py-1 text-gray-400 text-right select-none w-[35px]">
                          <div className="text-xs font-mono"></div>
                        </td>
                        <td className="px-2 py-1 text-gray-400 text-right select-none w-[35px]">
                          <div className="text-xs font-mono">{line.newLineNo}</div>
                        </td>
                      </>
                    )}
                    <td className="px-3 py-1 text-green-300 w-[20px] border-gray-600">
                      <span className="font-bold">+</span>
                    </td>
                    <td className="px-3 py-1 text-white">
                      <div className="font-mono text-sm leading-relaxed">
                        <ins>{line.addedContent || ' '}</ins>
                      </div>
                    </td>
                  </tr>
                </React.Fragment>
              );
            }

            return (
              <tr key={index} className={getRowClass()}>
                {showLineNumbers && (
                  <>
                    <td className="px-2 py-1 text-gray-400 text-right select-none w-[35px]">
                      <div className="text-xs font-mono">{line.oldLineNo || ''}</div>
                    </td>
                    <td className="px-2 py-1 text-gray-400 text-right select-none w-[35px] border-r border-gray-600">
                      <div className="text-xs font-mono">{line.newLineNo || ''}</div>
                    </td>
                  </>
                )}
                <td
                  className={`px-3 py-1 w-[20px] ${
                    line.type === 'added'
                      ? 'text-green-300'
                      : line.type === 'deleted'
                        ? 'text-red-300'
                        : 'text-gray-500'
                  }`}
                >
                  <span className="font-bold">
                    {line.type === 'added' ? '+' : line.type === 'deleted' ? '-' : ' '}
                  </span>
                </td>
                <td
                  className={`px-3 py-1 ${
                    line.type === 'added' || line.type === 'deleted'
                      ? 'text-white'
                      : 'text-gray-200'
                  }`}
                >
                  <div className="font-mono text-sm leading-relaxed break-all">
                    {line.content || '\u00A0'}
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
};

// 新しい差分表示コンポーネント（GitHubライク）
const SmartDiffValue = ({
  label,
  fieldInfo,
  isMarkdown = false,
}: {
  label: string;
  fieldInfo: DiffFieldInfo;
  isMarkdown?: boolean;
}) => {
  const renderValue = (value: any) => {
    if (value === null || value === undefined) return '(なし)';
    if (typeof value === 'boolean') return value ? 'はい' : 'いいえ';
    if (typeof value === 'number') return value.toString();
    return value;
  };

  const renderContent = (content: string, isMarkdown: boolean) => {
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
      console.error('マークダウン変換エラー:', error);
      return content;
    }
  };

  // マークダウンの本文フィールドには行ベース差分を使用
  if (isMarkdown && label === '本文') {
    if (fieldInfo.status === 'modified') {
      return (
        <div className="mb-6">
          <label className="block text-base font-semibold text-gray-200 mb-3">{label}</label>
          <LineDiffDisplay
            oldText={renderValue(fieldInfo.original)}
            newText={renderValue(fieldInfo.current)}
          />
        </div>
      );
    }

    if (fieldInfo.status === 'added') {
      return (
        <div className="mb-6">
          <label className="block text-base font-semibold text-gray-200 mb-3">{label}</label>
          <LineDiffDisplay oldText="" newText={renderValue(fieldInfo.current)} />
        </div>
      );
    }

    if (fieldInfo.status === 'deleted') {
      return (
        <div className="mb-6">
          <label className="block text-base font-semibold text-gray-200 mb-3">{label}</label>
          <LineDiffDisplay oldText={renderValue(fieldInfo.original)} newText="" />
        </div>
      );
    }
  }

  return (
    <div className="mb-6">
      <label className="block text-base font-semibold text-gray-200 mb-3">{label}</label>

      {fieldInfo.status === 'added' && (
        <div className="bg-green-900/30 border border-green-700 rounded-md p-3 text-sm text-green-200">
          {renderContent(renderValue(fieldInfo.current), isMarkdown)}
        </div>
      )}

      {fieldInfo.status === 'deleted' && (
        <div className="bg-red-900/30 border border-red-700 rounded-md p-3 text-sm text-red-200">
          {renderContent(renderValue(fieldInfo.original), isMarkdown)}
        </div>
      )}

      {fieldInfo.status === 'modified' && (
        <div className="space-y-1">
          <div className="bg-red-900/30 border border-red-700 rounded-md p-3 text-sm text-red-200">
            <span className="text-red-400 text-xs font-medium mr-2">-</span>
            {renderContent(renderValue(fieldInfo.original), isMarkdown)}
          </div>
          <div className="bg-green-900/30 border border-green-700 rounded-md p-3 text-sm text-green-200">
            <span className="text-green-400 text-xs font-medium mr-2">+</span>
            {renderContent(renderValue(fieldInfo.current), isMarkdown)}
          </div>
        </div>
      )}

      {fieldInfo.status === 'unchanged' && (
        <div className="bg-gray-800 border border-gray-600 rounded-md p-3 text-sm text-gray-300">
          {renderContent(renderValue(fieldInfo.current || fieldInfo.original), isMarkdown)}
        </div>
      )}
    </div>
  );
};

// 階層パンくずリストコンポーネント
const CategoryPathBreadcrumb = ({ categoryPath }: { categoryPath: string | null | undefined }) => {
  // categoryPathがnullまたはundefinedの場合は"/"を表示
  if (!categoryPath) {
    return (
      <div className="mb-6">
        <div className="flex items-center text-sm text-gray-400 mb-3">
          <span className="text-gray-500">/</span>
        </div>
        <div className="border-b border-gray-700/50"></div>
      </div>
    );
  }

  // category_pathをそのまま表示し、階層構造をパンくずリストとして表示
  const pathParts = categoryPath.split('/').filter(part => part.length > 0);

  return (
    <div className="mb-6">
      <div className="flex items-center text-sm text-gray-400 mb-3">
        <span className="text-gray-500">/</span>
        {pathParts.map((part, index) => (
          <React.Fragment key={index}>
            {index > 0 && (
              <span className="mx-2">
                <svg
                  className="w-3 h-3"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  xmlns="http://www.w3.org/2000/svg"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    d="M9 5l7 7-7 7"
                  ></path>
                </svg>
              </span>
            )}
            {index === pathParts.length - 1 ? (
              <span className="text-blue-400 font-medium">{part}</span>
            ) : (
              <span className="text-gray-400 hover:text-gray-300">{part}</span>
            )}
            {index < pathParts.length - 1 && <span className="mx-1">/</span>}
          </React.Fragment>
        ))}
      </div>
      <div className="border-b border-gray-700/50"></div>
    </div>
  );
};

/**
 * 差分確認画面コンポーネント
 */
export default function DiffPage(): JSX.Element {
  const [isLoading, setIsLoading] = useState(true);

  const [diffData, setDiffData] = useState<DiffResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState<string | null>(null);
  const [prTitle, setPrTitle] = useState('');
  const [prDescription, setPrDescription] = useState('');
  const [selectedReviewers, setSelectedReviewers] = useState<number[]>([]);
  const [showReviewerModal, setShowReviewerModal] = useState(false);
  const [reviewerSearch, setReviewerSearch] = useState('');
  const reviewerModalRef = useRef<HTMLDivElement | null>(null);
  const [users, setUsers] = useState<any[]>([]);
  const [loadingUsers, setLoadingUsers] = useState(false);

  // ユーザー一覧を取得する関数
  const handleFetchUser = async (searchEmail?: string) => {
    setLoadingUsers(true);
    try {
      const endpoint = searchEmail
        ? `${API_CONFIG.ENDPOINTS.PULL_REQUEST_REVIEWERS.GET}?email=${encodeURIComponent(searchEmail)}`
        : API_CONFIG.ENDPOINTS.PULL_REQUEST_REVIEWERS.GET;

      const response = await apiClient.get(endpoint);
      setUsers(response.users || []);
    } catch (error) {
      console.error('ユーザー取得エラー:', error);
      setUsers([]);
    } finally {
      setLoadingUsers(false);
    }
  };

  // レビュアーモーダルが表示された時にユーザー一覧を取得
  useEffect(() => {
    if (showReviewerModal) {
      handleFetchUser();
    }
  }, [showReviewerModal]);

  // レビュアー検索時の処理
  useEffect(() => {
    if (showReviewerModal && reviewerSearch) {
      const timeoutId = setTimeout(() => {
        handleFetchUser(reviewerSearch);
      }, 300);

      return () => clearTimeout(timeoutId);
    }
  }, [reviewerSearch, showReviewerModal]);

  useEffect(() => {
    const fetchDiff = async () => {
      try {

        const response = await apiClient.get(
          `${API_CONFIG.ENDPOINTS.USER_BRANCHES.GET_DIFF}`
        );

        setDiffData(response);
      } catch (err) {
        console.error('差分取得エラー:', err);
        setError('差分データの取得に失敗しました');
      } finally {
        setIsLoading(false);
      }
    };

    fetchDiff();
  }, []);

  useEffect(() => {
    if (!showReviewerModal) return;
    const handleClickOutside = (event: MouseEvent) => {
      if (reviewerModalRef.current && !reviewerModalRef.current.contains(event.target as Node)) {
        setShowReviewerModal(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [showReviewerModal]);

  // PR作成のハンドラー
  const handleSubmitPR = async () => {
    setIsSubmitting(true);
    setSubmitError(null);
    setSubmitSuccess(null);

    try {
      // URLパラメータからuser_branch_idを取得
      const urlParams = new URLSearchParams(window.location.search);
      const userBranchId = urlParams.get('user_branch_id');

      if (!userBranchId) {
        setSubmitError('user_branch_idパラメータが必要です');
        return;
      }

      // diffアイテムを構築
      const diffItems: ApiDiffItem[] = [];

      // ドキュメントバージョンを追加
      if (diffData?.document_versions) {
        diffData.document_versions.forEach(doc => {
          diffItems.push({
            id: doc.id,
            type: 'document',
          });
        });
      }

      // カテゴリを追加
      if (diffData?.document_categories) {
        diffData.document_categories.forEach(cat => {
          diffItems.push({
            id: cat.id,
            type: 'category',
          });
        });
      }

      // レビュアーのメールアドレスを取得
      const reviewerEmails =
        selectedReviewers.length > 0
          ? users.filter(user => selectedReviewers.includes(user.id)).map(user => user.email)
          : undefined;

      // デバッグログ
      console.log('送信データ:', {
        user_branch_id: parseInt(userBranchId),
        title: prTitle || '更新内容の提出',
        description: prDescription || 'このPRはハンドブックの更新を含みます。',
        diff_items: diffItems,
        reviewers: reviewerEmails,
        selectedReviewers,
        users: users.map(u => ({ id: u.id, email: u.email })),
      });

      // PRタイトル・説明をAPIに渡す
      const response = await createPullRequest({
        user_branch_id: parseInt(userBranchId),
        title: prTitle || '更新内容の提出',
        description: prDescription || 'このPRはハンドブックの更新を含みます。',
        diff_items: diffItems,
        reviewers: reviewerEmails,
      });

      if (response.success) {
        const successMessage = response.pr_url
          ? `差分の提出が完了しました。PR: ${response.pr_url}`
          : '差分の提出が完了しました';
        setSubmitSuccess(successMessage);
        // 3秒後にドキュメント一覧に戻る
        setTimeout(() => {
          window.location.href = '/documents';
        }, 3000);
      } else {
        setSubmitError(response.message || '差分の提出に失敗しました');
      }
    } catch (err) {
      console.error('差分提出エラー:', err);
      setSubmitError('差分の提出中にエラーが発生しました');
    } finally {
      setIsSubmitting(false);
    }
  };

  // 差分データをIDでマップ化
  const getDiffInfoById = (id: number, type: 'document' | 'category'): DiffDataInfo | null => {
    if (!diffData?.diff_data) return null;
    return diffData.diff_data.find(diff => diff.id === id && diff.type === type) || null;
  };

  // フィールド情報を取得（差分データがない場合は未変更として扱う）
  const getFieldInfo = (
    diffInfo: DiffDataInfo | null,
    fieldName: string,
    currentValue: any,
    originalValue?: any
  ): DiffFieldInfo => {
    if (!diffInfo) {
      return {
        status: 'unchanged',
        current: currentValue,
        original: originalValue,
      };
    }

    // 削除されたアイテムの場合、すべてのフィールドを削除済みとして扱う
    if (diffInfo.operation === 'deleted') {
      return {
        status: 'deleted',
        current: null,
        original: originalValue,
      };
    }

    if (!diffInfo.changed_fields[fieldName]) {
      return {
        status: 'unchanged',
        current: currentValue,
        original: originalValue,
      };
    }
    return diffInfo.changed_fields[fieldName];
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

  // エラー表示
  if (error) {
    return (
      <AdminLayout title="差分確認">
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
          <button
            className="px-4 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none"
            onClick={() => (window.location.href = '/documents')}
          >
            ドキュメント一覧に戻る
          </button>
        </div>
      </AdminLayout>
    );
  }

  // データが空の場合
  if (
    !diffData ||
    (diffData.document_categories.length === 0 && diffData.document_versions.length === 0)
  ) {
    return (
      <AdminLayout title="差分確認">
        <div className="flex flex-col items-center justify-center h-full">
          <p className="text-gray-400 mb-4">変更された内容はありません</p>
          <button
            className="px-4 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none"
            onClick={() => (window.location.href = '/documents')}
          >
            ドキュメント一覧に戻る
          </button>
        </div>
      </AdminLayout>
    );
  }

  // original/currentをslugでマッピング
  const mapBySlug = (arr: DiffItem[]) => Object.fromEntries(arr.map(item => [item.slug, item]));

  const originalDocs = mapBySlug(diffData.original_document_versions || []);
  const originalCats = mapBySlug(diffData.original_document_categories || []);

  return (
    <AdminLayout title="差分確認">
      <style>{markdownStyles}</style>
      <div className="flex flex-col h-full">
        {/* 成功メッセージ */}
        {submitSuccess && (
          <div className="mb-4 p-3 bg-green-900/50 border border-green-800 rounded-md text-green-200">
            <div className="flex items-center">
              <svg
                className="w-5 h-5 mr-2 text-green-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M5 13l4 4L19 7"
                />
              </svg>
              <span>{submitSuccess}</span>
            </div>
            <p className="text-sm mt-2">3秒後にドキュメント一覧に戻ります...</p>
            {submitSuccess.includes('PR:') && (
              <p className="text-sm mt-1">
                <a
                  href={submitSuccess.split('PR: ')[1]}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-blue-300 hover:text-blue-200 underline"
                >
                  PRを開く
                </a>
              </p>
            )}
          </div>
        )}

        {/* エラーメッセージ */}
        {submitError && (
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
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
              <span>{submitError}</span>
            </div>
          </div>
        )}

        {/* PR作成セクション（画像のようなデザイン） */}
        <div className="mb-20 w-full rounded-lg relative">
          {/* タイトル入力欄とレビュアーを重ねて配置 */}
          <div className="mb-6 relative w-full">
            <div className="mb-6 relative max-w-3xl w-full">
              <label className="block text-white text-base font-medium mb-3">タイトル</label>
              <input
                type="text"
                className="w-full px-4 py-3 pr-40 rounded-lg border border-gray-600 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                placeholder=""
                value={prTitle}
                onChange={e => setPrTitle(e.target.value)}
                disabled={isSubmitting}
              />
            </div>

            <div className="absolute right-0 top-0 flex flex-col items-start mr-20">
              <div className="flex items-center gap-40 relative" ref={reviewerModalRef}>
                <span className="text-white text-base font-bold">レビュアー</span>
                <Settings
                  className="w-5 h-5 text-gray-300 ml-2 cursor-pointer"
                  onClick={() => setShowReviewerModal(v => !v)}
                />
                {showReviewerModal && (
                  <div className="absolute left-0 top-full z-50 mt-2 w-full bg-[#181A1B] rounded-xl border border-gray-700 shadow-2xl">
                    <div className="flex flex-col">
                      <div className="px-5 pt-5 pb-2 border-b border-gray-700">
                        <div className="flex justify-between items-center mb-2">
                          <span className="text-white font-semibold text-base">
                            最大15人までリクエストできます
                          </span>
                        </div>
                        <input
                          type="text"
                          className="w-full px-3 py-2 rounded bg-[#222426] border border-gray-600 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"
                          placeholder="Type or choose a user"
                          value={reviewerSearch}
                          onChange={e => setReviewerSearch(e.target.value)}
                          autoFocus
                        />
                      </div>
                      {/* Suggestionsセクション */}
                      <div className="px-5 pt-3">
                        <div className="text-xs text-gray-400 font-semibold mb-2">Suggestions</div>
                        {loadingUsers ? (
                          <div className="text-gray-500 text-sm py-2">読み込み中...</div>
                        ) : users.length === 0 ? (
                          <div className="text-gray-500 text-sm py-2">ユーザーが見つかりません</div>
                        ) : (
                          users
                            .filter(user =>
                              user.email.toLowerCase().includes(reviewerSearch.toLowerCase())
                            )
                            .map(user => (
                              <div
                                key={user.id}
                                className={`flex items-center gap-3 px-2 py-2 rounded cursor-pointer hover:bg-[#23272d] ${selectedReviewers.includes(user.id) ? 'bg-[#23272d]' : ''}`}
                                onClick={() =>
                                  setSelectedReviewers(
                                    selectedReviewers.includes(user.id)
                                      ? selectedReviewers.filter(id => id !== user.id)
                                      : [...selectedReviewers, user.id]
                                  )
                                }
                              >
                                <span className="text-2xl">👤</span>
                                <div className="flex-1 min-w-0">
                                  <div className="text-white font-medium leading-tight">
                                    {user.email}
                                  </div>
                                  <div className="text-xs text-gray-400 truncate">
                                    {user.role || 'editor'}
                                  </div>
                                </div>
                              </div>
                            ))
                        )}
                      </div>
                    </div>
                  </div>
                )}
              </div>
              {selectedReviewers.length === 0 ? (
                <p className="text-white text-base font-medium mt-5 text-sm">レビュアーなし</p>
              ) : (
                <div className="mt-5">
                  <div className="space-y-1">
                    {selectedReviewers.map(reviewerId => {
                      const user = users.find(u => u.id === reviewerId);
                      return user ? (
                        <div key={reviewerId} className="flex items-center gap-2 text-sm">
                          <span className="text-xl">👤</span>
                          <span className="text-gray-300">{user.email}</span>
                        </div>
                      ) : null;
                    })}
                  </div>
                </div>
              )}
            </div>
            <div className="mb-8">
              <div className="mb-6 relative max-w-3xl w-full">
                <label className="block text-white text-base font-medium mb-3 max-w-3xl">
                  本文
                </label>
                <textarea
                  className="w-full px-4 py-3 rounded-lg border border-gray-600 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"
                  placeholder=""
                  rows={5}
                  value={prDescription}
                  onChange={e => setPrDescription(e.target.value)}
                  disabled={isSubmitting}
                />
              </div>
            </div>

            <div className="flex gap-4 justify-end max-w-3xl">
              <button
                className="px-6 py-2.5 bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none text-white font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                onClick={() => (window.location.href = '/documents')}
                disabled={isSubmitting}
              >
                戻る
              </button>
              <button
                className="px-6 py-2.5 bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none flex items-center text-white font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                onClick={handleSubmitPR}
                disabled={isSubmitting}
              >
                {isSubmitting ? (
                  <>
                    <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mr-2"></div>
                    <span>差分を提出中...</span>
                  </>
                ) : (
                  <span>差分を提出する</span>
                )}
              </button>
            </div>
          </div>
        </div>

        {/* 変更されたカテゴリの詳細 */}
        {diffData.document_categories.length > 0 && (
          <div className="mb-10">
            <h2 className="text-xl font-bold mb-6 flex items-center border-b border-gray-700 pb-3">
              <Folder className="w-5 h-5 mr-2" />
              カテゴリの変更 × {diffData.document_categories.length}
            </h2>
            <div className="space-y-6 mr-20">
              {diffData.document_categories.map(category => {
                const diffInfo = getDiffInfoById(category.id, 'category');
                const originalCategory = originalCats[category.slug];

                return (
                  <div
                    key={category.id}
                    className="bg-gray-900/70 rounded-lg border border-gray-800 p-6 shadow-lg"
                  >
                    <CategoryPathBreadcrumb categoryPath={category.category_path} />
                    <SmartDiffValue
                      label="タイトル"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'title',
                        category.title,
                        originalCategory?.title
                      )}
                    />
                    <SmartDiffValue
                      label="説明"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'description',
                        category.description,
                        originalCategory?.description
                      )}
                    />
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* ドキュメントの変更数表示 */}
        <h2 className="text-xl font-bold mb-6 flex items-center">
          <DocumentDetailed className="w-6 h-6 mr-2" />
          ドキュメントの変更 × {diffData.document_versions.length}
        </h2>

        {/* 変更されたドキュメントの詳細 */}
        {diffData.document_versions.length > 0 && (
          <div className="mb-8 mr-20">
            <div className="space-y-6">
              {diffData.document_versions.map(document => {
                const diffInfo = getDiffInfoById(document.id, 'document');
                const originalDocument = originalDocs[document.slug];
                
                // ドキュメントのカテゴリのcategory_pathを取得
                const documentCategory = diffData.document_categories.find(cat => cat.id === document.category_id);
                const documentCategoryPath = documentCategory?.category_path;

                return (
                  <div
                    key={document.id}
                    className="bg-gray-900/70 rounded-lg border border-gray-800 p-6 shadow-lg"
                  >
                    <CategoryPathBreadcrumb categoryPath={documentCategoryPath} />
                    <SmartDiffValue
                      label="Slug"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'slug',
                        document.slug,
                        originalDocument?.slug
                      )}
                    />
                    <SmartDiffValue
                      label="タイトル"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'sidebar_label',
                        document.sidebar_label,
                        originalDocument?.sidebar_label
                      )}
                    />
                    <SmartDiffValue
                      label="公開設定"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'is_public',
                        document.status === 'published',
                        originalDocument?.status === 'published'
                      )}
                    />
                    <SmartDiffValue
                      label="本文"
                      fieldInfo={getFieldInfo(
                        diffInfo,
                        'content',
                        document.content,
                        originalDocument?.content
                      )}
                      isMarkdown
                    />
                  </div>
                );
              })}
            </div>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
