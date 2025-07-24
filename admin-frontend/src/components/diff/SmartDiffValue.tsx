import React from 'react';
import { markdownToHtml } from '@/utils/markdownToHtml';
import { DiffDisplay } from './DiffDisplay';
import type { DiffFieldInfo } from '@/types/diff';

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
        <div
          className="border border-gray-600 rounded-md p-3 text-sm diff-added-container"
          style={{ backgroundColor: 'rgba(63, 185, 80, 0.3)', color: '#ffffff' }}
        >
          {renderContent(renderValue(fieldInfo.current), isMarkdown)}
        </div>
      )}

      {fieldInfo.status === 'deleted' && (
        <div
          className="border border-gray-600 rounded-md p-3 text-sm diff-deleted-container"
          style={{ backgroundColor: 'rgba(248, 81, 73, 0.25)', color: '#ff6b6b' }}
        >
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
