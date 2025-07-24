import React, { useState } from 'react';
import { markdownToHtml } from '@/utils/markdownToHtml';
import {
  generateDiffHtml,
  generatePatchInfo,
  insertDiffMarkersInText,
  replaceDiffMarkersInHtml,
} from '@/utils/diffUtils';

// GitHub風差分表示コンポーネント（マークダウンをリッチテキストで差分表示）
interface DiffDisplayProps {
  originalText: string;
  currentText: string;
  isMarkdown?: boolean;
  showPatchInfo?: boolean;
}

export const DiffDisplay: React.FC<DiffDisplayProps> = ({
  originalText,
  currentText,
  isMarkdown = false,
  showPatchInfo = false,
}) => {
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
