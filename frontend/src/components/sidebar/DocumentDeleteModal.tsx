import React from 'react';

interface DocumentDeleteModalProps {
  isOpen: boolean;
  onClose: () => void;
  onDelete: () => void;
  documentName: string;
}

/**
 * ドキュメント削除確認モーダル
 */
export default function DocumentDeleteModal({
  isOpen,
  onClose,
  onDelete,
  documentName
}: DocumentDeleteModalProps) {
  if (!isOpen) return null;

  const handleDelete = () => {
    onDelete();
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* オーバーレイ */}
      <div 
        className="fixed inset-0 bg-black bg-opacity-50"
        onClick={onClose}
      />
      
      {/* モーダルコンテンツ */}
      <div className="relative bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4 border border-gray-600">
        <p className="text-gray-300 mb-6">
          「{documentName}」を削除しますか？。
        </p>
        
        <div className="flex justify-end gap-3">
          <button
            onClick={onClose}
            className="px-4 py-2 text-gray-300 hover:text-white transition-colors"
          >
            キャンセル
          </button>
          <button
            onClick={handleDelete}
            className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors"
          >
            削除
          </button>
        </div>
      </div>
    </div>
  );
}
