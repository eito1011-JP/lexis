import React from 'react';

interface DescriptionEditProps {
  value: string;
  onChange: (value: string) => void;
  onCancel: () => void;
  onSave: () => void;
  isUpdating: boolean;
}

/**
 * プルリクエストの説明を編集するためのコンポーネント
 */
export const DescriptionEdit: React.FC<DescriptionEditProps> = ({
  value,
  onChange,
  onCancel,
  onSave,
  isUpdating,
}) => {
  return (
    <div className="ml-[-1rem]">
      <textarea
        value={value}
        onChange={e => onChange(e.target.value)}
        className="w-full px-3 py-2 bg-[#0D1117] border border-blue-500 rounded-md text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 resize-none"
        placeholder="Leave a comment"
        rows={6}
        autoFocus
      />
      <div className="flex justify-end gap-3 mt-3">
        <button
          onClick={onCancel}
          className="px-4 py-2 bg-transparent hover:bg-gray-700 text-red-400 rounded-md transition-colors border border-gray-600"
          disabled={isUpdating}
        >
          キャンセル
        </button>
        <button
          onClick={onSave}
          disabled={isUpdating}
          className="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition-colors disabled:bg-gray-500 disabled:cursor-not-allowed"
        >
          {isUpdating ? '更新中...' : '保存'}
        </button>
      </div>
    </div>
  );
};
