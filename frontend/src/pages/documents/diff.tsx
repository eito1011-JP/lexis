import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { client } from '@/api/client';
import { Folder } from '@/components/icon/common/Folder';
import React from 'react';
import { markdownToHtml } from '@/utils/markdownToHtml';
import { DocumentDetailed } from '@/components/icon/common/DocumentDetailed';
import { Breadcrumb } from '@/components/common/Breadcrumb';
import { type BreadcrumbItem } from '@/api/categoryHelpers';
import { markdownStyles } from '@/styles/markdownContent';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { SubmitChangesForm } from '@/components/diff/SubmitChangesForm';
import { useUserMe } from '@/hooks/useUserMe';

type FieldChangeInfo = {
  status: 'added' | 'deleted' | 'modified' | 'unchanged';
  current: any;
  original: any;
};

type DiffInfo = {
  id: number;
  type: 'category' | 'document';
  operation: 'created' | 'updated' | 'deleted';
  changed_fields: Record<string, FieldChangeInfo>;
  snapshots?: {
    current?: {
      breadcrumbs?: BreadcrumbItem[];
      data?: any;
    };
    original?: {
      breadcrumbs?: BreadcrumbItem[];
      data?: any;
    };
  };
};

type DiffResponse = {
  diff: DiffInfo[];
  user_branch_id: number;
  organization_id: number;
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
  fieldInfo: FieldChangeInfo;
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


/**
 * 差分確認画面コンポーネント
 */
export default function DiffPage(): JSX.Element {
  const [isLoading, setIsLoading] = useState(true);
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const userBranchId = searchParams.get('user_branch_id');
  const [diffData, setDiffData] = useState<DiffResponse | null>(null);
  const [error, setError] = useState<string | null>(null);


  useEffect(() => {
    const fetchDiff = async () => {
      if (!userBranchId) {
        setError('ユーザーブランチIDが指定されていません');
        setIsLoading(false);
        return;
      }

      try {
        const response = await client.user_branches.diff.$get({
          query: { user_branch_id: Number(userBranchId) }
        });

        console.log('差分データ:', response);
        // 差分データが存在しない、または変更がない場合はドキュメント一覧にリダイレクト
        if (!response.diff || response.diff.length === 0) {
          navigate('/documents');
          return;
        }

        // DiffResponseの型に合わせてデータをセット
        const diffResponse: DiffResponse = {
          diff: response.diff,
          user_branch_id: response.user_branch_id || Number(userBranchId),
          organization_id: response.organization_id || 0
        };
        setDiffData(diffResponse);
      } catch (err) {
        console.error('差分取得エラー:', err);
        setError('差分データの取得に失敗しました');
      } finally {
        setIsLoading(false);
      }
    };

    fetchDiff();
  }, [navigate, userBranchId]);

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
  if (!diffData || !diffData.diff || diffData.diff.length === 0) {
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

  // カテゴリと ドキュメントの差分を分離
  const categoryDiffs = diffData.diff.filter(d => d.type === 'category');
  const documentDiffs = diffData.diff.filter(d => d.type === 'document');

  return (
    <AdminLayout title="差分確認">
      <style>{markdownStyles}</style>
      <div className="flex flex-col h-full">
        {/* 差分提出フォーム */}
        <SubmitChangesForm
          organizationId={diffData.organization_id}
          userBranchId={diffData.user_branch_id}
        />

        {/* 変更されたカテゴリの詳細 */}
        {categoryDiffs.length > 0 && (
          <div className="mb-10">
            <h2 className="text-xl font-bold mb-6 flex items-center border-b border-gray-700 pb-3">
              <Folder className="w-5 h-5 mr-2" />
              カテゴリの変更 × {categoryDiffs.length}
            </h2>
            <div className="space-y-6 mr-20">
              {categoryDiffs.map((diff, index) => {
                // changed_fieldsから各フィールドの情報を取得
                const titleFieldInfo: FieldChangeInfo = diff.changed_fields?.title || {
                  status: 'unchanged',
                  current: null,
                  original: null,
                };

                const descriptionFieldInfo: FieldChangeInfo = diff.changed_fields?.description || {
                  status: 'unchanged',
                  current: null,
                  original: null,
                };

                return (
                  <div
                    key={`category-${diff.id || index}`}
                    className="bg-gray-900/70 rounded-lg border border-gray-800 p-6 shadow-lg"
                  >
                    {/* パンクズリスト追加 */}
                    {diff.snapshots?.current?.breadcrumbs && (
                      <div className="mb-4">
                        <Breadcrumb breadcrumbs={diff.snapshots.current.breadcrumbs} />
                      </div>
                    )}
                    <SmartDiffValue
                      label="タイトル"
                      fieldInfo={titleFieldInfo}
                    />
                    <SmartDiffValue
                      label="説明"
                      fieldInfo={descriptionFieldInfo}
                      isMarkdown={true}
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
          ドキュメントの変更 × {documentDiffs.length}
        </h2>

        {/* 変更されたドキュメントの詳細 */}
        {documentDiffs.length > 0 && (
          <div className="mb-8 mr-20">
            <div className="space-y-6">
              {documentDiffs.map((diff, index) => {
                // changed_fieldsから各フィールドの情報を取得
                const titleFieldInfo: FieldChangeInfo = diff.changed_fields?.title || {
                  status: 'unchanged',
                  current: null,
                  original: null,
                };

                const descriptionFieldInfo: FieldChangeInfo = diff.changed_fields?.description || {
                  status: 'unchanged',
                  current: null,
                  original: null,
                };

                return (
                  <div
                    key={`document-${diff.id || index}`}
                    className="bg-gray-900/70 rounded-lg border border-gray-800 p-6 shadow-lg"
                  >
                    {/* パンクズリスト追加 */}
                    {diff.snapshots?.current?.breadcrumbs && (
                      <div className="mb-4">
                        <Breadcrumb breadcrumbs={diff.snapshots.current.breadcrumbs} />
                      </div>
                    )}
                    <SmartDiffValue
                      label="タイトル"
                      fieldInfo={titleFieldInfo}
                    />
                    <SmartDiffValue
                      label="説明"
                      fieldInfo={descriptionFieldInfo}
                      isMarkdown={true}
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
