import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  fetchPullRequestDetail,
  approvePullRequest,
  startPullRequestEditSession,
  type PullRequestDetailResponse,
} from '@/api/pullRequest';
import { DocumentDetailed } from '@/components/icon/common/DocumentDetailed';
import { Folder } from '@/components/icon/common/Folder';
import { markdownStyles } from '@/styles/markdownContent';
import { PULL_REQUEST_STATUS } from '@/constants/pullRequestStatus';
import { mapBySlug } from '@/utils/diffUtils';
import { diffStyles } from '@/styles/diffStyles';
import { StatusBanner } from '@/components/diff/StatusBanner';
import { ConfirmationActionDropdown } from '@/components/diff/ConfirmationActionDropdown';
import type {
  DiffItem,
  DiffFieldInfo,
  DiffDataInfo,
  TabType,
  ConfirmationAction,
} from '@/types/diff';
import { TABS } from '@/types/diff';
import React from 'react';
import { markdownToHtml } from '@/utils/markdownToHtml';
import { Breadcrumb } from '@/components/common/Breadcrumb';

// プラスアイコンコンポーネント
const PlusIcon = ({ className }: { className?: string }) => (
  <svg
    className={className}
    width="16"
    height="16"
    viewBox="0 0 16 16"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
  >
    <path
      d="M8 2V14M2 8H14"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

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
  const [hoveredLineIndex, setHoveredLineIndex] = React.useState<number | null>(null);
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
                  <tr
                    className="bg-red-900/50 border-red-700 hover:bg-red-900/70 transition-colors cursor-pointer group"
                    onMouseEnter={() => setHoveredLineIndex(index * 2)}
                    onMouseLeave={() => setHoveredLineIndex(null)}
                  >
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
                    <td className="px-3 py-1 w-[20px] relative">
                      {hoveredLineIndex === index * 2 ? (
                        <div className="flex items-center justify-center w-4 h-4 bg-blue-600 text-white rounded-sm transition-all duration-150">
                          <PlusIcon className="w-3 h-3" />
                        </div>
                      ) : (
                        <span className="font-bold text-red-300">-</span>
                      )}
                    </td>
                    <td className="px-3 py-1 text-white">
                      <div className="font-mono text-sm leading-relaxed">
                        <del>{line.deletedContent || ' '}</del>
                      </div>
                    </td>
                  </tr>
                  {/* 追加行 */}
                  <tr
                    className="bg-green-900/50 border-green-700 hover:bg-green-900/70 transition-colors cursor-pointer group"
                    onMouseEnter={() => setHoveredLineIndex(index * 2 + 1)}
                    onMouseLeave={() => setHoveredLineIndex(null)}
                  >
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
                    <td className="px-3 py-1 w-[20px] border-gray-600 relative">
                      {hoveredLineIndex === index * 2 + 1 ? (
                        <div className="flex items-center justify-center w-4 h-4 bg-blue-600 text-white rounded-sm transition-all duration-150">
                          <PlusIcon className="w-3 h-3" />
                        </div>
                      ) : (
                        <span className="font-bold text-green-300">+</span>
                      )}
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

            const isInteractiveLine =
              line.type === 'added' || line.type === 'deleted' || line.type === 'unchanged';
            const baseRowClass = getRowClass();
            const hoverClass = isInteractiveLine
              ? line.type === 'added'
                ? 'hover:bg-green-900/70 cursor-pointer'
                : line.type === 'deleted'
                  ? 'hover:bg-red-900/70 cursor-pointer'
                  : 'hover:bg-gray-800/70 cursor-pointer'
              : '';

            return (
              <tr
                key={index}
                className={`${baseRowClass} ${hoverClass} transition-colors group`}
                onMouseEnter={() => (isInteractiveLine ? setHoveredLineIndex(index + 10000) : null)}
                onMouseLeave={() => setHoveredLineIndex(null)}
              >
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
                <td className="px-3 py-1 w-[20px] relative">
                  {hoveredLineIndex === index + 10000 && isInteractiveLine ? (
                    <div className="flex items-center justify-center w-4 h-4 bg-blue-600 text-white rounded-sm transition-all duration-150">
                      <PlusIcon className="w-3 h-3" />
                    </div>
                  ) : (
                    <span
                      className={`font-bold ${
                        line.type === 'added'
                          ? 'text-green-300'
                          : line.type === 'deleted'
                            ? 'text-red-300'
                            : 'text-gray-500'
                      }`}
                    >
                      {line.type === 'added' ? '+' : line.type === 'deleted' ? '-' : ' '}
                    </span>
                  )}
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

export default function ChangeSuggestionDiffPage(): JSX.Element {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const [pullRequestData, setPullRequestData] = useState<PullRequestDetailResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<TabType>('changes');
  const [conflictStatus] = useState<{
    mergeable: boolean | null;
    mergeable_state: string | null;
  }>({ mergeable: null, mergeable_state: null });
  const [selectedConfirmationAction, setSelectedConfirmationAction] = useState<ConfirmationAction>(
    'create_correction_request'
  );

  // 差分データをIDでマップ化する関数
  const getDiffInfoById = (id: number, type: 'document' | 'category'): DiffDataInfo | null => {
    if (!pullRequestData?.diff_data) return null;
    return (
      pullRequestData.diff_data.find(
        (diff: DiffDataInfo) => diff.id === id && diff.type === type
      ) || null
    );
  };

  // フィールド情報を取得する関数
  const getFieldInfo = (
    diffInfo: DiffDataInfo | null,
    fieldName: string,
    currentValue: any,
    originalValue?: any
  ): DiffFieldInfo => {
    if (!diffInfo) {
      return {
        status: 'unchanged' as const,
        current: currentValue,
        original: originalValue,
      };
    }

    if (diffInfo.operation === 'deleted') {
      return {
        status: 'deleted' as const,
        current: null,
        original: originalValue,
      };
    }

    if (!diffInfo.changed_fields[fieldName]) {
      return {
        status: 'unchanged' as const,
        current: currentValue,
        original: originalValue,
      };
    }

    return diffInfo.changed_fields[fieldName];
  };

  useEffect(() => {
    const fetchData = async () => {
      if (!id) {
        setError('プルリクエストIDが指定されていません');
        return;
      }

      try {
        const data = await fetchPullRequestDetail(id);
        console.log('data', data);
        setPullRequestData(data);
      } catch (err) {
        console.error('プルリクエスト詳細取得エラー:', err);
        setError('プルリクエスト詳細の取得に失敗しました');
      } finally {
      }
    };

    fetchData();
  }, [id]);

  // エラー表示
  if (error) {
    return (
      <AdminLayout 
        title="エラー"
        sidebar={true}
        showDocumentSideContent={false}
      >
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

  if (!pullRequestData) {
    return (
      <AdminLayout 
        title="変更内容詳細"
        sidebar={true}
        showDocumentSideContent={true}
      >
        <div className="flex flex-col items-center justify-center h-full">
          <p className="text-gray-400">データが見つかりません</p>
        </div>
      </AdminLayout>
    );
  }

  // 確認アクションの処理
  const handleConfirmationAction = async () => {
    if (!id) return;

    switch (selectedConfirmationAction) {
      case 'create_correction_request':
        // 修正リクエスト作成画面に遷移
        window.location.href = `/change-suggestions/${id}/fix-request`;
        break;
      case 're_edit_proposal':
        try {
          // プルリクエスト編集セッションを開始
          const sessionResponse = await startPullRequestEditSession(id);

          // セッション情報をローカルストレージに保存
          localStorage.setItem('pullRequestEditToken', sessionResponse.token);

          // 変更提案の再編集画面に遷移
          navigate(
            `/documents?edit_pull_request_id=${id}&pull_request_edit_token=${sessionResponse.token}`
          );
        } catch (error) {
          console.error('編集セッション開始エラー:', error);
          setError('編集セッションの開始に失敗しました');
        }
        break;
      case 'approve_changes':
        try {
          const result = await approvePullRequest(id);
          if (result.success) {
            // 承認成功時にアクティビティページに遷移
            window.location.href = `/change-suggestions/${id}`;
          } else {
            setError(result.error || '変更の承認に失敗しました');
          }
        } catch (err) {
          console.error('承認エラー:', err);
          setError('変更の承認に失敗しました');
        }
        break;
    }
  };

  return (
    <AdminLayout 
      title="変更内容詳細"
      sidebar={true}
      showDocumentSideContent={true}
    >
      <style>{markdownStyles}</style>
      <style>{diffStyles}</style>
      <div className="mb-20 w-full rounded-lg relative">
        {/* ステータスバナー */}
        {(pullRequestData.status === PULL_REQUEST_STATUS.MERGED ||
          pullRequestData.status === PULL_REQUEST_STATUS.OPENED ||
          pullRequestData.status === PULL_REQUEST_STATUS.CLOSED ||
          conflictStatus.mergeable === false) && (
          <StatusBanner
            status={pullRequestData.status}
            authorEmail={pullRequestData.author_email}
            createdAt={pullRequestData.created_at}
            conflict={conflictStatus.mergeable === false}
            title={pullRequestData.title}
          />
        )}

        {/* 確認アクションボタン */}
        <div className="flex justify-end mb-6">
          <ConfirmationActionDropdown
            selectedAction={selectedConfirmationAction}
            onActionChange={setSelectedConfirmationAction}
            onConfirm={handleConfirmationAction}
          />
        </div>

        {/* タブナビゲーション */}
        <div className="mb-8">
          <nav className="flex">
            {TABS.map(tab => (
              <button
                key={tab.id}
                onClick={() => {
                  if (tab.id === 'activity') {
                    window.location.href = `/change-suggestions/${id}`;
                  } else {
                    setActiveTab(tab.id);
                  }
                }}
                className={`py-2 px-4 font-medium text-sm transition-colors ${
                  activeTab === tab.id
                    ? 'text-white border border-white border-b-0 rounded-t-lg'
                    : 'text-white hover:text-gray-300 hover:bg-gray-800 border-b border-white'
                }`}
              >
                <span className="mr-2">{tab.icon}</span>
                {tab.label}
              </button>
            ))}
          </nav>

          {/* タブ下の長い水平線 */}
          <div className="w-full h-px bg-white mt-0"></div>
        </div>

        {/* 変更内容タブ */}
        {pullRequestData && (
          <>
            {(() => {
              const originalDocs = mapBySlug(pullRequestData.original_document_versions || []);
              const originalCats = mapBySlug(pullRequestData.original_document_categories || []);

              return (
                <>
                  {/* カテゴリの変更 */}
                  {pullRequestData.document_categories.length > 0 && (
                    <div className="mb-10">
                      <h2 className="text-xl font-bold mb-6 flex items-center border-b border-gray-700 pb-3">
                        <Folder className="w-5 h-5 mr-2" />
                        カテゴリの変更 × {pullRequestData.document_categories.length}
                      </h2>
                      <div className="space-y-6 mr-20">
                        {pullRequestData.document_categories.map((category: DiffItem) => {
                          const diffInfo = getDiffInfoById(category.id, 'category');
                          const originalCategory = originalCats[category.slug];

                          return (
                            <div
                              key={category.id}
                              className="bg-gray-900/70 rounded-lg border border-gray-800 p-6 shadow-lg"
                            >
                              {/* パンクズリスト追加 */}
                              {diffItem.snapshots?.current?.breadcrumbs && (
                                <div className="mb-4">
                                  <Breadcrumb breadcrumbs={diffItem.snapshots.current.breadcrumbs} />
                                </div>
                              )}
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
                    ドキュメントの変更 × {pullRequestData.document_versions.length}
                  </h2>

                  {/* ドキュメントの変更 */}
                  {pullRequestData.document_versions.length > 0 && (
                    <div className="mb-8 mr-20">
                      <div className="space-y-6">
                        {pullRequestData.document_versions.map((document: DiffItem) => {
                          const diffInfo = getDiffInfoById(document.id, 'document');
                          const originalDocument = originalDocs[document.slug];

                          return (
                            <div
                              key={document.id}
                              className="bg-gray-900/70 rounded-lg border border-gray-800 p-6 shadow-lg"
                            >
                              {/* パンクズリスト追加 */}
                              {diffItem.snapshots?.current?.breadcrumbs && (
                                <div className="mb-4">
                                  <Breadcrumb breadcrumbs={diffItem.snapshots.current.breadcrumbs} />
                                </div>
                              )}
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
                </>
              );
            })()}
          </>
        )}
      </div>
    </AdminLayout>
  );
}
