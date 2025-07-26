import React from 'react';
import { markdownToHtml } from '@/utils/markdownToHtml';
import type { DiffFieldInfo } from '@/types/diff';
import { makeDiff, cleanupSemantic } from '@sanity/diff-match-patch';

interface SmartDiffValueProps {
  label: string;
  fieldInfo: DiffFieldInfo;
  isMarkdown?: boolean;
}

export const SmartDiffValue: React.FC<SmartDiffValueProps> = ({
  label,
  fieldInfo,
  isMarkdown = false,
}) => {
  const renderValue = (value: any) => {
    if (value === null || value === undefined) return '';
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

  // originalとcurrentの値を直接比較して表示内容を決定
  const getDisplayContent = () => {
    const { original, current } = fieldInfo;

    // originalとcurrentがどちらもnull/undefinedの場合
    if (
      (original === null || original === undefined) &&
      (current === null || current === undefined)
    ) {
      return {
        leftContent: '',
        rightContent: '',
        hasChanges: false,
      };
    }

    // originalがnull/undefinedでcurrentに値がある場合（新規追加）
    if (
      (original === null || original === undefined) &&
      current !== null &&
      current !== undefined
    ) {
      const currentStr = renderValue(current);

      if (isMarkdown) {
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
            hasChanges: true,
          };
        } catch (error) {
          console.warn('マークダウン新規追加表示エラー:', error);
          // エラーの場合はプレーンテキストで処理
        }
      }

      // プレーンテキストの新規追加処理
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
        hasChanges: true,
      };
    }

    // currentがnull/undefinedでoriginalに値がある場合（削除）
    if (
      (current === null || current === undefined) &&
      original !== null &&
      original !== undefined
    ) {
      const originalStr = renderValue(original);

      if (isMarkdown) {
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
            rightContent: '(削除済み)',
            hasChanges: true,
          };
        } catch (error) {
          console.warn('マークダウン削除表示エラー:', error);
          // エラーの場合はプレーンテキストで処理
        }
      }

      // プレーンテキストの削除処理
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
        rightContent: '(削除済み)',
        hasChanges: true,
      };
    }

    // 両方に値がある場合は差分を計算
    return generateSplitDiffContent(original, current, isMarkdown);
  };

  const { leftContent, rightContent } = getDisplayContent();

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

        {/* 変更提案 */}
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
