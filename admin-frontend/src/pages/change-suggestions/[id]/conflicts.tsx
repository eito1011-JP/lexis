import React, { useEffect, useMemo, useState, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import AdminLayout from '@/components/admin/layout';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { fetchConflictDiffs, type ConflictFileDiff } from '@/api/pullRequest';
import MarkdownEditor from '@/components/admin/editor/SlateEditor';

// front-matterを除去して本文のみを取得する関数
const extractBodyContent = (content: string | null): string => {
  if (!content) return '';

  // front-matterの開始と終了を検出
  const lines = content.split('\n');
  let bodyStartIndex = 0;

  // ---で囲まれたfront-matterをスキップ
  if (lines[0]?.trim() === '---') {
    for (let i = 1; i < lines.length; i++) {
      if (lines[i]?.trim() === '---') {
        bodyStartIndex = i + 1;
        break;
      }
    }
  }

  return lines.slice(bodyStartIndex).join('\n').trim();
};

// front-matter（--- で囲まれた先頭ブロック）を素朴にパース
type FrontMatter = Record<string, string | boolean | number>;

const extractFrontMatter = (content: string | null): FrontMatter => {
  if (!content) return {};
  const lines = content.split('\n');
  if (lines[0]?.trim() !== '---') return {};
  const fmLines: string[] = [];
  for (let i = 1; i < lines.length; i++) {
    const line = lines[i];
    if (line?.trim() === '---') break;
    fmLines.push(line);
  }
  const result: FrontMatter = {};
  for (const raw of fmLines) {
    const idx = raw.indexOf(':');
    if (idx === -1) continue;
    const key = raw.slice(0, idx).trim();
    let value = raw.slice(idx + 1).trim();
    if (!key) continue;
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }
    if (value === 'true') {
      result[key] = true;
    } else if (value === 'false') {
      result[key] = false;
    } else if (!Number.isNaN(Number(value)) && value !== '') {
      result[key] = Number(value);
    } else {
      result[key] = value;
    }
  }
  return result;
};
const getFileOrderFromFrontMatter = (fm: FrontMatter): string => {
  const fileOrder = fm['file_order'];

  if (typeof fileOrder === 'number') {
    return fileOrder.toString();
  }

  return '';
};

const getTitleFromFrontMatter = (fm: FrontMatter): string => {
  const sidebarLabel = fm['sidebar_label'];

  if (typeof sidebarLabel === 'string' && sidebarLabel.trim()) {
    return sidebarLabel;
  }

  return '';
};

const getPublishLabelFromFrontMatter = (fm: FrontMatter): string => {
  // draft / published / status のいずれかを参照（存在順優先）
  const draft = fm['draft'];
  const published = fm['published'];
  const status = fm['status'];

  if (typeof draft === 'boolean') return draft ? '下書き' : '公開';
  if (typeof published === 'boolean') return published ? '公開' : '非公開';
  if (typeof status === 'string') return status;
  return '';
};

// docs/配下のパスからフォルダ名~ファイル名（.md拡張子を除く）を取得
const extractDisplaySlug = (filename: string): string => {
  if (!filename.startsWith('docs/')) return filename;

  // docs/を除去
  const pathWithoutDocs = filename.substring(5);

  // .md拡張子を除去
  const pathWithoutExt = pathWithoutDocs.replace(/\.md$/, '');

  return pathWithoutExt;
};

// front-matter の slug を優先し、無ければパスから導出
const getSlugFromFrontMatterOrPath = (fm: FrontMatter, filename: string): string => {
  const slug = fm['slug'];
  if (typeof slug === 'string' && slug.trim()) return slug.trim();
  return extractDisplaySlug(filename);
};

// diff.tsxと同じ行単位の差分表示
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

  const calculateLineDiff = (oldText: string, newText: string) => {
    const oldLines = oldText ? oldText.split('\n') : [];
    const newLines = newText ? newText.split('\n') : [];

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
      const nextCommon = commonIndex < commonLines.length ? commonLines[commonIndex] : null;

      if (nextCommon && oldIndex === nextCommon.oldIndex && newIndex === nextCommon.newIndex) {
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
        result.push({
          type: 'deleted',
          content: oldLines[oldIndex],
          oldLineNo: oldLineNo,
          newLineNo: undefined,
        });
        oldIndex++;
        oldLineNo++;
      } else if (nextCommon && newIndex < nextCommon.newIndex) {
        result.push({
          type: 'added',
          content: newLines[newIndex],
          oldLineNo: undefined,
          newLineNo: newLineNo,
        });
        newIndex++;
        newLineNo++;
      } else {
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
              return (
                <React.Fragment key={index}>
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

// 2つのテキストの行差分を計算（コンフリクト組み立て用）
const calculateLineDiffForMerge = (oldText: string, newText: string) => {
  const oldLines = oldText ? oldText.split('\n') : [];
  const newLines = newText ? newText.split('\n') : [];

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
  type DiffLine = {
    type: 'added' | 'deleted' | 'unchanged' | 'change';
    content: string;
    deletedContent?: string;
    addedContent?: string;
  };
  const result: DiffLine[] = [];

  let oldIndex = 0;
  let newIndex = 0;
  let commonIndex = 0;

  while (oldIndex < oldLines.length || newIndex < newLines.length) {
    const nextCommon = commonIndex < commonLines.length ? commonLines[commonIndex] : null;

    if (nextCommon && oldIndex === nextCommon.oldIndex && newIndex === nextCommon.newIndex) {
      result.push({ type: 'unchanged', content: oldLines[oldIndex] });
      oldIndex++;
      newIndex++;
      commonIndex++;
    } else if (nextCommon && oldIndex < nextCommon.oldIndex && newIndex < nextCommon.newIndex) {
      result.push({
        type: 'change',
        content: '',
        deletedContent: oldLines[oldIndex],
        addedContent: newLines[newIndex],
      });
      oldIndex++;
      newIndex++;
    } else if (nextCommon && oldIndex < nextCommon.oldIndex) {
      result.push({ type: 'deleted', content: oldLines[oldIndex] });
      oldIndex++;
    } else if (nextCommon && newIndex < nextCommon.newIndex) {
      result.push({ type: 'added', content: newLines[newIndex] });
      newIndex++;
    } else {
      if (oldIndex < oldLines.length) {
        result.push({ type: 'deleted', content: oldLines[oldIndex] });
        oldIndex++;
      }
      if (newIndex < newLines.length) {
        result.push({ type: 'added', content: newLines[newIndex] });
        newIndex++;
      }
    }
  }

  return result;
};

// base と head の差分から、エディタに表示するコンフリクト付きテキストを生成
const buildConflictMarkedContent = (baseBody: string, headBody: string) => {
  const diff = calculateLineDiffForMerge(baseBody, headBody);
  const outputLines: string[] = [];
  let inConflict = false;
  let headBlock: string[] = [];
  let baseBlock: string[] = [];

  const flushConflict = () => {
    if (!inConflict) return;
    outputLines.push('<<<<<<< 変更提案の差分');
    outputLines.push(...headBlock);
    outputLines.push('=======');
    outputLines.push(...baseBlock);
    outputLines.push('>>>>>>> 最新の差分');
    inConflict = false;
    headBlock = [];
    baseBlock = [];
  };

  for (const line of diff) {
    if (line.type === 'unchanged') {
      flushConflict();
      outputLines.push(line.content);
    } else {
      // 競合領域
      inConflict = true;
      if (line.type === 'change') {
        if (line.addedContent !== undefined) headBlock.push(line.addedContent);
        if (line.deletedContent !== undefined) baseBlock.push(line.deletedContent);
      }
      if (line.type === 'added') headBlock.push(line.content);
      if (line.type === 'deleted') baseBlock.push(line.content);
    }
  }
  flushConflict();
  return outputLines.join('\n');
};

const ConflictResolutionPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { isLoading } = useSessionCheck('/login', false);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [files, setFiles] = useState<ConflictFileDiff[]>([]);
  const [editedContents, setEditedContents] = useState<Record<string, string>>({});

  useEffect(() => {
    let mounted = true;
    const run = async () => {
      if (!id) return;
      setLoading(true);
      setError(null);
      try {
        const res = await fetchConflictDiffs(id);
        console.log('res', res);
        if (!mounted) return;
        setFiles(res.files || []);
      } catch (e: any) {
        if (!mounted) return;
        setError(e.message || 'コンフリクト差分の取得に失敗しました');
      } finally {
        if (mounted) setLoading(false);
      }
    };
    run();
    return () => {
      mounted = false;
    };
  }, [id]);

  const leftList = useMemo(() => files.map(f => f.filename), [files]);

  // 左側のslug表示用（docs/配下のフォルダ名~ファイル名、.md拡張子を除く）
  const leftListDisplay = useMemo(() => files.map(f => extractDisplaySlug(f.filename)), [files]);

  if (isLoading) {
    return (
      <AdminLayout title="読み込み中..." sidebar={false}>
        <div className="flex items-center justify-center h-full">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white"></div>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout title="変更の競合詳細" sidebar={false}>
      {loading && (
        <div className="w-full mb-4">
          <div className="text-sm text-gray-400 bg-[#151515] border border-gray-700 rounded-md px-3 py-2">
            この操作には少し時間がかかることがあります。対象ファイル数が少ない場合は高速化しています。
          </div>
        </div>
      )}
      <div className="flex gap-6">
        {/* 左: 変更のぶつかり一覧 */}
        <div className="w-64">
          <div className="text-white font-semibold mb-2">変更のぶつかり一覧</div>
          <div className="space-y-2">
            {leftList.length === 0 && (
              <div className="text-gray-400 text-sm">対象ファイルがありません</div>
            )}
            {leftListDisplay.map((name, index) => (
              <div
                key={files[index].filename}
                className="text-xs px-3 py-2 rounded border border-gray-700 text-white bg-[#171717]"
              >
                {name}
              </div>
            ))}
          </div>
        </div>

        {/* 右: ファイルごとの3カラム比較 */}
        <div className="flex-1">
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
            <h3 className="text-xl font-bold">変更の競合修正</h3>
          </div>
          <div className="text-2xl font-bold text-white mb-4">初めてのPR提出</div>

          {error && (
            <div className="mb-4 p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
              {error}
            </div>
          )}

          {loading ? (
            <div className="flex items-center justify-center h-40">
              <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white"></div>
            </div>
          ) : (
            <div className="space-y-10">
              {files.map((file, idx) => {
                const baseFm = extractFrontMatter(file.baseText);
                const headFm = extractFrontMatter(file.headText);
                const headSlug = file.headText
                  ? getSlugFromFrontMatterOrPath(headFm, file.filename)
                  : '';
                const baseSlug = file.baseText
                  ? getSlugFromFrontMatterOrPath(baseFm, file.filename)
                  : '';
                const slugConflict = !!headSlug && !!baseSlug && headSlug !== baseSlug;
                const getFieldClass = (hasValue: boolean, isConflict: boolean) =>
                  `w-full px-3 py-2 rounded border ${
                    isConflict
                      ? 'border-[#6600FF] bg-[rgba(102,0,255,0.15)] text-white'
                      : hasValue
                        ? 'border-gray-700 bg-[#111113] text-white'
                        : 'border-red-700 bg-red-900/30 text-red-200'
                  }`;
                return (
                  <div key={file.filename + idx} className="border border-gray-700 rounded-lg p-5">
                    <div className="grid grid-cols-2 gap-4 mb-4">
                      <div>
                        <div className="text-gray-400 text-xs mb-2">Slug</div>
                        <input
                          className={getFieldClass(!!headSlug, slugConflict)}
                          value={headSlug}
                          readOnly
                        />
                      </div>
                      <div>
                        <div className="text-gray-400 text-xs mb-2">Slug</div>
                        <input
                          className={getFieldClass(!!baseSlug, slugConflict)}
                          value={baseSlug}
                          readOnly
                        />
                      </div>
                    </div>
                    {(() => {
                      const baseFileOrder = getFileOrderFromFrontMatter(baseFm);
                      const headFileOrder = getFileOrderFromFrontMatter(headFm);
                      const conflict =
                        !!headFileOrder && !!baseFileOrder && headFileOrder !== baseFileOrder;
                      return (
                        <div className="grid grid-cols-2 gap-4 mb-4">
                          <div>
                            <div className="text-gray-400 text-xs mb-2">表示順序</div>
                            <input
                              className={getFieldClass(!!headFileOrder, conflict)}
                              readOnly
                              value={headFileOrder}
                            />
                          </div>
                          <div>
                            <div className="text-gray-400 text-xs mb-2">表示順序</div>
                            <input
                              className={getFieldClass(!!baseFileOrder, conflict)}
                              readOnly
                              value={baseFileOrder}
                            />
                          </div>
                        </div>
                      );
                    })()}

                    {(() => {
                      const baseTitle = getTitleFromFrontMatter(baseFm);
                      const headTitle = getTitleFromFrontMatter(headFm);
                      const conflict = !!headTitle && !!baseTitle && headTitle !== baseTitle;
                      return (
                        <div className="grid grid-cols-2 gap-4 mb-4">
                          <div>
                            <div className="text-gray-400 text-xs mb-2">タイトル</div>
                            <input
                              className={getFieldClass(!!headTitle, conflict)}
                              readOnly
                              value={headTitle}
                            />
                          </div>
                          <div>
                            <div className="text-gray-400 text-xs mb-2">タイトル</div>
                            <input
                              className={getFieldClass(!!baseTitle, conflict)}
                              readOnly
                              value={baseTitle}
                            />
                          </div>
                        </div>
                      );
                    })()}

                    {(() => {
                      const basePub = getPublishLabelFromFrontMatter(baseFm);
                      const headPub = getPublishLabelFromFrontMatter(headFm);
                      const conflict = !!headPub && !!basePub && headPub !== basePub;
                      return (
                        <div className="grid grid-cols-2 gap-4 mb-4">
                          <div>
                            <div className="text-gray-400 text-xs mb-2">公開設定</div>
                            <input
                              className={getFieldClass(!!headPub, conflict)}
                              readOnly
                              value={headPub}
                            />
                          </div>
                          <div>
                            <div className="text-gray-400 text-xs mb-2">公開設定</div>
                            <input
                              className={getFieldClass(!!basePub, conflict)}
                              readOnly
                              value={basePub}
                            />
                          </div>
                        </div>
                      );
                    })()}

                    {/* 本文: 左=変更提案の本文（head）、右=ベースの本文（base） */}
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <div className="text-gray-400 text-xs mb-2">本文</div>
                        {(() => {
                          const headBody = extractBodyContent(file.headText);
                          const baseBody = extractBodyContent(file.baseText);
                          const conflict = !!headBody && !!baseBody && headBody !== baseBody;
                          return (
                            <div
                              className={`border rounded w-full px-3 py-2 whitespace-pre-wrap font-mono text-sm min-h-10 ${
                                conflict
                                  ? 'border-[#6600FF] bg-[rgba(102,0,255,0.15)] text-white'
                                  : headBody
                                    ? 'border-gray-700 bg-[#111113] text-white'
                                    : 'border-red-700 bg-red-900/30 text-red-200'
                              }`}
                            >
                              {headBody}
                            </div>
                          );
                        })()}
                      </div>
                      <div>
                        <div className="text-gray-400 text-xs mb-2">本文</div>
                        {(() => {
                          const headBody = extractBodyContent(file.headText);
                          const baseBody = extractBodyContent(file.baseText);
                          const conflict = !!headBody && !!baseBody && headBody !== baseBody;
                          return (
                            <div
                              className={`border rounded px-3 py-2 whitespace-pre-wrap font-mono text-sm min-h-10 ${
                                conflict
                                  ? 'border-[#6600FF] bg-[rgba(102,0,255,0.15)] text-white'
                                  : baseBody
                                    ? 'border-gray-700 bg-[#111113] text-white'
                                    : 'border-red-700 bg-red-900/30 text-red-200'
                              }`}
                            >
                              {baseBody}
                            </div>
                          );
                        })()}
                      </div>
                    </div>

                    {/* 下: コンフリクト編集エディタ */}
                    <div className="mt-6">
                      <div className="text-gray-400 text-xs mb-2">本文（編集用）</div>
                      <div className="bg-[#111113] border border-gray-700 rounded-md p-2">
                        {(() => {
                          const baseBody = extractBodyContent(file.baseText);
                          const headBody = extractBodyContent(file.headText);
                          const conflictMarked = buildConflictMarkedContent(baseBody, headBody);
                          const currentContent = editedContents[file.filename] ?? conflictMarked;
                          return (
                            <>
                              <MarkdownEditor
                                initialContent={currentContent}
                                onChange={() => {}}
                                onMarkdownChange={md =>
                                  setEditedContents(prev => ({ ...prev, [file.filename]: md }))
                                }
                              />
                              <div className="flex gap-2 mt-3">
                                <button
                                  className="px-3 py-1 text-xs rounded bg-green-700 hover:bg-green-600 text-white border border-green-600"
                                  onClick={() =>
                                    setEditedContents(prev => ({
                                      ...prev,
                                      [file.filename]: headBody,
                                    }))
                                  }
                                >
                                  現在の差分を採用
                                </button>
                                <button
                                  className="px-3 py-1 text-xs rounded bg-blue-700 hover:bg-blue-600 text-white border border-blue-600"
                                  onClick={() =>
                                    setEditedContents(prev => ({
                                      ...prev,
                                      [file.filename]: baseBody,
                                    }))
                                  }
                                >
                                  最新の差分を適用する
                                </button>
                                <button
                                  className="px-3 py-1 text-xs rounded bg-gray-700 hover:bg-gray-600 text-white border border-gray-600"
                                  onClick={() => {
                                    const both = [headBody, '=======', baseBody].join('\n');
                                    setEditedContents(prev => ({ ...prev, [file.filename]: both }));
                                  }}
                                >
                                  両方の差分を採用する
                                </button>
                              </div>
                            </>
                          );
                        })()}
                      </div>
                    </div>
                  </div>
                );
              })}

              <div className="flex justify-center">
                <button
                  className="px-6 py-2 bg-[#3832A5] hover:bg-blue-600 text-white rounded-md"
                  onClick={() => navigate(`/admin/change-suggestions/${id}`)}
                >
                  保存
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </AdminLayout>
  );
};

export default ConflictResolutionPage;
