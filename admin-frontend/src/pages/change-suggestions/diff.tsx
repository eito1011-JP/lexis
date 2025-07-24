import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { useParams } from 'react-router-dom';
import {
  fetchPullRequestDetail,
  approvePullRequest,
  type PullRequestDetailResponse,
} from '@/api/pullRequest';
import { markdownToHtml } from '@/utils/markdownToHtml';
import React from 'react';
import { DocumentDetailed } from '@/components/icon/common/DocumentDetailed';
import { Folder } from '@/components/icon/common/Folder';
import { markdownStyles } from '@/styles/markdownContent';
import { formatDistanceToNow } from 'date-fns';
import ja from 'date-fns/locale/ja';
import { PULL_REQUEST_STATUS } from '@/constants/pullRequestStatus';
import { Merge } from '@/components/icon/common/Merge';
import { Merged } from '@/components/icon/common/Merged';
import { Closed } from '@/components/icon/common/Closed';
import { ChevronDown } from '@/components/icon/common/ChevronDown';
import { makeDiff, cleanupSemantic, makePatches, stringifyPatches } from '@sanity/diff-match-patch';

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

// タブ定義
type TabType = 'activity' | 'changes';

const TABS = [
  { id: 'activity' as TabType, label: 'アクティビティ', icon: '💬' },
  { id: 'changes' as TabType, label: '変更内容', icon: '📝' },
] as const;

// 差分計算とHTML生成の関数
const generateDiffHtml = (originalText: string, currentText: string): string => {
  // makeDiffを使って差分のタプル配列を作成
  const diffs = makeDiff(originalText || '', currentText || '');
  
  // より読みやすい差分にするため、意味的なクリーンアップを実行
  const cleanedDiffs = cleanupSemantic(diffs);
  
  // カスタムHTMLレンダリング
  let html = '';
  for (const [operation, text] of cleanedDiffs) {
    const escapedText = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\n/g, '<br/>');
    
    switch (operation) {
      case -1: // 削除
        html += `<span class="diff-deleted-content">${escapedText}</span>`;
        break;
      case 1: // 追加
        html += `<span class="diff-added-content">${escapedText}</span>`;
        break;
      case 0: // 変更なし
        html += escapedText;
        break;
    }
  }
  
  return html;
};

// パッチ情報を生成する関数（デバッグや詳細表示用）
const generatePatchInfo = (originalText: string, currentText: string): string => {
  try {
    // makePatches でパッチ配列を作成
    const patches = makePatches(originalText || '', currentText || '');
    
    // stringifyPatches で unidiff形式の文字列に変換
    const patchString = stringifyPatches(patches);
    
    return patchString;
  } catch (error) {
    console.error('パッチ生成エラー:', error);
    return '';
  }
};

// マークダウンテキストに差分マーカーを挿入する関数
const insertDiffMarkersInText = (originalText: string, currentText: string): string => {
  const diffs = makeDiff(originalText || '', currentText || '');
  const cleanedDiffs = cleanupSemantic(diffs);
  
  let markedText = '';
  cleanedDiffs.forEach(([operation, text]) => {
    if (operation === -1) {
      // 削除された部分にマーカーを追加
      markedText += `<DIFF_DELETE>${text}</DIFF_DELETE>`;
    } else if (operation === 1) {
      // 追加された部分にマーカーを追加
      markedText += `<DIFF_ADD>${text}</DIFF_ADD>`;
    } else {
      // 変更なし
      markedText += text;
    }
  });
  
  // デバッグ用：差分マーカーの確認
  if (process.env.NODE_ENV === 'development') {
    console.log('Original:', originalText);
    console.log('Current:', currentText);
    console.log('Marked Text:', markedText);
  }
  
  return markedText;
};

// HTMLに変換後、差分マーカーを適切なspanタグに置換する関数
const replaceDiffMarkersInHtml = (html: string): string => {
  // デバッグ用：処理前のHTMLを確認
  if (process.env.NODE_ENV === 'development') {
    console.log('HTML before processing:', html);
  }
  
  let processedHtml = html;
  
  // 複数要素にまたがる差分マーカーを検出して処理
  // 実際のパターン: <li>要素2<DIFF_DELETE></li>\n<li>要素3</DIFF_DELETE></li>
  // 注意: 要素2は差分対象ではなく、改行+要素3のみが削除対象
  processedHtml = processedHtml.replace(
    /(<li[^>]*>)([^<]*)<DIFF_DELETE><\/li>\s*(<li[^>]*>)([^<]*)<\/DIFF_DELETE>/g,
    (match: string, li1Tag: string, content1: string, li2Tag: string, content2: string) => {
      // 2番目のli要素のみにクラスを追加（削除対象は要素3のみ）
      const li2WithClass = li2Tag.includes('class=')
        ? li2Tag.replace(/class="([^"]*)"/, 'class="$1 diff-deleted-item"')
        : li2Tag.replace('>', ' class="diff-deleted-item">');
      
      // 1番目の要素は通常表示、2番目の要素のみ差分表示
      return `${li1Tag}${content1}</li>\n${li2WithClass}<span class="diff-deleted-content">${content2}</span></li>`;
    }
  );
  
  processedHtml = processedHtml.replace(
    /(<li[^>]*>)([^<]*)<DIFF_ADD><\/li>\s*(<li[^>]*>)([^<]*)<\/DIFF_ADD>/g,
    (match: string, li1Tag: string, content1: string, li2Tag: string, content2: string) => {
      // 2番目のli要素のみにクラスを追加（追加対象は要素3のみ）
      const li2WithClass = li2Tag.includes('class=')
        ? li2Tag.replace(/class="([^"]*)"/, 'class="$1 diff-added-item"')
        : li2Tag.replace('>', ' class="diff-added-item">');
      
      // 1番目の要素は通常表示、2番目の要素のみ差分表示
      return `${li1Tag}${content1}</li>\n${li2WithClass}<span class="diff-added-content">${content2}</span></li>`;
    }
  );
  
  // より複雑なケース：複数のli要素にまたがる場合
  processedHtml = processedHtml.replace(
    /<DIFF_DELETE>([\s\S]*?)<\/DIFF_DELETE>/g,
    (match: string, content: string) => {
      // 内部にli要素が含まれている場合の処理
      if (content.includes('<li>') || content.includes('</li>')) {
        // li要素ごとに分割して処理
        return content.replace(
          /(<li)([^>]*>)(.*?)(<\/li>)/g,
          (liMatch: string, openTagStart: string, attributes: string, liContent: string, closeTag: string) => {
            // li要素全体に差分クラスを適用（マーカーも含めて色を変更）
            const existingClass = attributes.match(/class="([^"]*)"/) || ['', ''];
            const newClass = existingClass[1] ? `${existingClass[1]} diff-deleted-item` : 'diff-deleted-item';
            const newAttributes = attributes.replace(/class="[^"]*"/, '').trim();
            
            return `${openTagStart} class="${newClass}"${newAttributes ? ' ' + newAttributes : ''}><span class="diff-deleted-content">${liContent}</span>${closeTag}`;
          }
        );
      } else {
        return `<span class="diff-deleted-content">${content}</span>`;
      }
    }
  );
  
  processedHtml = processedHtml.replace(
    /<DIFF_ADD>([\s\S]*?)<\/DIFF_ADD>/g,
    (match: string, content: string) => {
      // 内部にli要素が含まれている場合の処理
      if (content.includes('<li>') || content.includes('</li>')) {
        // li要素ごとに分割して処理
        return content.replace(
          /(<li)([^>]*>)(.*?)(<\/li>)/g,
          (liMatch: string, openTagStart: string, attributes: string, liContent: string, closeTag: string) => {
            // li要素全体に差分クラスを適用（マーカーも含めて色を変更）
            const existingClass = attributes.match(/class="([^"]*)"/) || ['', ''];
            const newClass = existingClass[1] ? `${existingClass[1]} diff-added-item` : 'diff-added-item';
            const newAttributes = attributes.replace(/class="[^"]*"/, '').trim();
            
            return `${openTagStart} class="${newClass}"${newAttributes ? ' ' + newAttributes : ''}><span class="diff-added-content">${liContent}</span>${closeTag}`;
          }
        );
      } else {
        return `<span class="diff-added-content">${content}</span>`;
      }
    }
  );
  
  // 単一要素内の通常の差分マーカーを置換
  processedHtml = processedHtml
    .replace(/<DIFF_DELETE>(.*?)<\/DIFF_DELETE>/gs, '<span class="diff-deleted-content">$1</span>')
    .replace(/<DIFF_ADD>(.*?)<\/DIFF_ADD>/gs, '<span class="diff-added-content">$1</span>');
  
  // デバッグ用：処理後のHTMLを確認
  if (process.env.NODE_ENV === 'development') {
    console.log('HTML after processing:', processedHtml);
  }
  
  return processedHtml;
};

// GitHub風差分表示コンポーネント（マークダウンをリッチテキストで差分表示）
const DiffDisplay: React.FC<{
  originalText: string;
  currentText: string;
  isMarkdown?: boolean;
  showPatchInfo?: boolean;
}> = ({ originalText, currentText, isMarkdown = false, showPatchInfo = false }) => {
  const [showPatch, setShowPatch] = useState(false);
  const patchInfo = showPatchInfo ? generatePatchInfo(originalText, currentText) : '';
  
  const DiffContent = () => {
    if (isMarkdown) {
      try {
        // テキストレベルで差分を計算し、カスタムマーカーを挿入
        const markedText = insertDiffMarkersInText(originalText || '', currentText || '');
        
        // マーカー付きテキストをHTMLに変換
        const htmlWithMarkers = markdownToHtml(markedText);
        
        // HTMLでマーカーを適切なspanタグに置換
        const finalHtml = replaceDiffMarkersInHtml(htmlWithMarkers);
        
        return (
          <div className="p-3 bg-gray-800 border border-gray-600 rounded-md text-sm">
            <div 
              className="markdown-content prose prose-invert max-w-none text-gray-300 prose-headings:text-white prose-p:text-gray-300 prose-strong:text-white prose-code:text-green-400 prose-pre:bg-gray-900 prose-blockquote:border-gray-600 prose-blockquote:text-gray-400"
              dangerouslySetInnerHTML={{ __html: finalHtml }}
            />
          </div>
        );
      } catch (error) {
        console.warn('マークダウン差分表示エラー:', error);
        // エラーの場合はフォールバック表示
        return (
          <div className="p-3 bg-gray-800 border border-gray-600 rounded-md text-sm">
            <div className="text-red-400 mb-2">マークダウン表示エラー - テキストモードで表示</div>
            <div 
              className="text-gray-300 whitespace-pre-wrap"
              dangerouslySetInnerHTML={{ __html: generateDiffHtml(originalText, currentText) }}
            />
          </div>
        );
      }
    }
    
    // プレーンテキストの場合は、従来通りの差分表示
    const diffHtml = generateDiffHtml(originalText, currentText);
    return (
      <div 
        className="p-3 bg-gray-800 border border-gray-600 rounded-md text-sm text-gray-300 whitespace-pre-wrap"
        dangerouslySetInnerHTML={{ __html: diffHtml }}
      />
    );
  };

  return (
    <div>
      <DiffContent />
      
      {/* パッチ情報表示機能 */}
      {showPatchInfo && patchInfo && (
        <div className="mt-2">
          <button
            onClick={() => setShowPatch(!showPatch)}
            className="text-xs text-blue-400 hover:text-blue-300 underline"
          >
            {showPatch ? 'パッチ情報を隠す' : 'パッチ情報を表示'}
          </button>
          
          {showPatch && (
            <div className="mt-2 p-2 bg-gray-900 border border-gray-700 rounded text-xs font-mono text-gray-400 overflow-x-auto">
              <div className="mb-1 text-gray-500">Unidiff形式のパッチ:</div>
              <pre className="whitespace-pre-wrap">{patchInfo}</pre>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

// SmartDiffValueコンポーネント
const SmartDiffValue: React.FC<{
  label: string;
  fieldInfo: DiffFieldInfo;
  isMarkdown?: boolean;
}> = ({ label, fieldInfo, isMarkdown = false }) => {
  const renderValue = (value: any) => {
    if (value === null || value === undefined) return '(なし)';
    if (typeof value === 'boolean') return value ? 'はい' : 'いいえ';
    return String(value);
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
      return content;
    }
  };

  return (
    <div className="mb-4">
      <label className="block text-sm font-medium text-gray-300 mb-2">{label}</label>

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
        <DiffDisplay 
          originalText={renderValue(fieldInfo.original)}
          currentText={renderValue(fieldInfo.current)}
          isMarkdown={isMarkdown}
          showPatchInfo={isMarkdown || label === 'Slug' || label === 'タイトル'} // マークダウンやキーフィールドでパッチ情報を表示
        />
      )}

      {fieldInfo.status === 'unchanged' && (
        <div className="bg-gray-800 border border-gray-600 rounded-md p-3 text-sm text-gray-300">
          {renderContent(renderValue(fieldInfo.current || fieldInfo.original), isMarkdown)}
        </div>
      )}
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

// ステータスバナーコンポーネント
const StatusBanner: React.FC<{
  status: string;
  authorEmail: string;
  createdAt: string;
  conflict: boolean;
  title: string;
}> = ({ status, authorEmail, createdAt, conflict, title }) => {
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
          {authorEmail}さんが{' '}
          {formatDistanceToNow(new Date(createdAt), { addSuffix: true, locale: ja })}{' '}
          に変更を提出しました
        </span>
      </div>
    </div>
  );
};

// 確認アクションの型定義
type ConfirmationAction = 'create_correction_request' | 're_edit_proposal' | 'approve_changes';

// ConfirmationActionDropdownコンポーネント
const ConfirmationActionDropdown: React.FC<{
  selectedAction: ConfirmationAction;
  onActionChange: (action: ConfirmationAction) => void;
  onConfirm: () => void;
}> = ({ selectedAction, onActionChange, onConfirm }) => {
  const [isOpen, setIsOpen] = useState(false);

  const actions = [
    {
      value: 'create_correction_request' as ConfirmationAction,
      label: '修正リクエストを作成',
    },
    {
      value: 're_edit_proposal' as ConfirmationAction,
      label: '変更提案を再編集する',
    },
    {
      value: 'approve_changes' as ConfirmationAction,
      label: '変更を承認する',
    },
  ];

  return (
    <div className="relative">
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center px-4 py-2 bg-gray-800 border border-gray-600 rounded-md text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <span>確認アクション</span>
        <ChevronDown className="w-4 h-4 ml-2" />
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-2 w-64 bg-gray-800 border border-gray-600 rounded-md shadow-lg z-10">
          <div className="p-4">
            <div className="space-y-3">
              {actions.map(action => (
                <label key={action.value} className="flex items-center cursor-pointer">
                  <input
                    type="radio"
                    name="confirmationAction"
                    value={action.value}
                    checked={selectedAction === action.value}
                    onChange={() => onActionChange(action.value)}
                    className="mr-3 text-blue-500 focus:ring-blue-500"
                  />
                  <span className="text-white text-sm">{action.label}</span>
                </label>
              ))}
            </div>
            <div className="mt-4 flex justify-end">
              <button
                type="button"
                onClick={() => {
                  onConfirm();
                  setIsOpen(false);
                }}
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                確定する
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default function ChangeSuggestionDiffPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/login', false);
  const { id } = useParams<{ id: string }>();

  // GitHub風の差分表示用CSSスタイル（改良版）
  const diffStyles = `
    /* コンテンツラッパー方式のスタイル */
    /* li要素全体に背景色を適用するため、spanの背景色は透明に */
    .diff-deleted-item .diff-deleted-content {
      background-color: transparent !important;
      padding: 0;
      display: inline;
      /* 文字色は通常色を維持してコントラストを保つ */
    }
    
    .diff-added-item .diff-added-content {
      background-color: transparent !important;
      padding: 0;
      display: inline;
      /* 文字色は通常色を維持してコントラストを保つ */
    }
    
    /* 通常の差分コンテンツ（li要素以外での使用） */
    .diff-deleted-content {
      background-color: rgba(248, 81, 73, 0.15) !important;
      border-radius: 3px;
      padding: 2px 6px;
      display: inline;
    }
    
    .diff-added-content {
      background-color: rgba(63, 185, 80, 0.15) !important;
      border-radius: 3px;
      padding: 2px 6px;
      display: inline;
    }
    
    /* Flexboxを使ったカスタムリストスタイル */
    ol {
      list-style: none !important;
      counter-reset: item;
      padding-left: 0 !important;
    }
    
    ol li {
      display: flex !important;
      align-items: flex-start;
      counter-increment: item;
      margin: 4px 0;
      line-height: 1.6;
    }
    
    ol li::before {
      content: counter(item) "." !important;
      min-width: 32px;
      text-align: right;
      padding-right: 8px;
      margin-right: 8px;
      flex-shrink: 0;
    }
    
    /* 削除差分要素のスタイル */
    .diff-deleted-item {
      margin: 2px 0;
      background-color: rgba(248, 81, 73, 0.15) !important;
      border-radius: 4px;
    }
    
    .diff-deleted-item::before {
      /* 番号部分の背景色は削除し、li要素全体の背景色のみ使用 */
      color: inherit;
      margin-right: 8px !important;
    }
    
    /* 追加差分要素のスタイル */
    .diff-added-item {
      margin: 2px 0;
      background-color: rgba(63, 185, 80, 0.15) !important;
      border-radius: 4px;
      padding: 4px 0;
    }
    
    .diff-added-item::before {
      /* 番号部分の背景色は削除し、li要素全体の背景色のみ使用 */
      color: inherit;
      margin-right: 8px !important;
    }
    
    /* 通常のリスト要素 */
    ol li:not(.diff-deleted-item):not(.diff-added-item)::before {
      color: inherit;
      background-color: transparent;
    }
    
    /* 従来のスタイルも保持（フォールバック用） */
    .diff-deleted {
      background-color: rgba(248, 81, 73, 0.25) !important;
      color: #ff9492 !important;
      border-radius: 3px;
      padding: 2px 4px;
      margin: 2px 0;
      display: inline;
    }
    
    .diff-added {
      background-color: rgba(63, 185, 80, 0.25) !important;
      color: #7ee787 !important;
      border-radius: 3px;
      padding: 2px 4px;
      margin: 2px 0;
      display: inline;
    }
    
    /* リスト要素の基本スタイル */
    ol, ul {
      margin: 16px 0;
      padding-left: 24px;
    }
    
    li {
      margin: 2px 0;
      line-height: 1.6;
    }
    
    /* 差分要素内のリストマーカーも適切に色付け */
    .diff-deleted-content li::marker {
      color: #ff9492;
    }
    
    .diff-added-content li::marker {
      color: #7ee787;
    }
  `;

  const [pullRequestData, setPullRequestData] = useState<PullRequestDetailResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<TabType>('changes');
  const [conflictStatus, setConflictStatus] = useState<{
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
        status: 'unchanged',
        current: currentValue,
        original: originalValue,
      };
    }

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

  // データをslugでマップ化する関数
  const mapBySlug = (items: DiffItem[]) => {
    return items.reduce(
      (acc, item) => {
        acc[item.slug] = item;
        return acc;
      },
      {} as Record<string, DiffItem>
    );
  };

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
      } catch (err) {
        console.error('プルリクエスト詳細取得エラー:', err);
        setError('プルリクエスト詳細の取得に失敗しました');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [id]);

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
      <AdminLayout title="変更内容詳細">
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

  if (!pullRequestData) {
    return (
      <AdminLayout title="変更内容詳細">
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
        window.location.href = `/admin/change-suggestions/${id}/fix-request`;
        break;
      case 're_edit_proposal':
        console.log('変更提案を再編集');
        // TODO: 変更提案の再編集画面への遷移
        break;
      case 'approve_changes':
        try {
          const result = await approvePullRequest(id);
          if (result.success) {
            // 承認成功時にアクティビティページに遷移
            window.location.href = `/admin/change-suggestions/${id}`;
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
    <AdminLayout title="変更内容詳細">
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
                    window.location.href = `/admin/change-suggestions/${id}`;
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
                      <h2 className="text-xl font-bold mb-4 flex items-center">
                        <Folder className="w-5 h-5 mr-2" />
                        カテゴリの変更 × {pullRequestData.document_categories.length}
                      </h2>
                      <div className="space-y-4">
                        {pullRequestData.document_categories.map((category: DiffItem) => {
                          const diffInfo = getDiffInfoById(category.id, 'category');
                          const originalCategory = originalCats[category.slug];

                          return (
                            <div
                              key={category.id}
                              className="bg-gray-900 rounded-lg border border-gray-800 p-6"
                            >
                              <SmartDiffValue
                                label="Slug"
                                fieldInfo={getFieldInfo(
                                  diffInfo,
                                  'slug',
                                  category.slug,
                                  originalCategory?.slug
                                )}
                              />
                              <SmartDiffValue
                                label="カテゴリ名"
                                fieldInfo={getFieldInfo(
                                  diffInfo,
                                  'sidebar_label',
                                  category.sidebar_label,
                                  originalCategory?.sidebar_label
                                )}
                              />
                              <SmartDiffValue
                                label="表示順"
                                fieldInfo={getFieldInfo(
                                  diffInfo,
                                  'position',
                                  category.position,
                                  originalCategory?.position
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

                  {/* ドキュメントの変更 */}
                  {pullRequestData.document_versions.length > 0 && (
                    <div>
                      <h2 className="text-xl font-bold mb-4 flex items-center">
                        <DocumentDetailed className="w-6 h-6 mr-2" />
                        ドキュメントの変更 × {pullRequestData.document_versions.length}
                      </h2>
                      <div className="space-y-6">
                        {pullRequestData.document_versions.map((document: DiffItem) => {
                          const diffInfo = getDiffInfoById(document.id, 'document');
                          const originalDocument = originalDocs[document.slug];

                          return (
                            <div
                              key={document.id}
                              className="bg-gray-900 rounded-lg border border-gray-800 p-6"
                            >
                              <SlugBreadcrumb slug={document.slug} />
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
                                  document.status === 'published' ? '公開する' : '公開しない',
                                  originalDocument?.status === 'published'
                                    ? '公開する'
                                    : '公開しない'
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