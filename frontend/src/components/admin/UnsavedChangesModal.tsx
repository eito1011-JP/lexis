import React from 'react';

interface UnsavedChangesModalProps {
  isOpen: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}

/**
 * 未保存の変更があることを警告するモーダルコンポーネント
 * 添付画像のデザインに基づいて実装
 */
export default function UnsavedChangesModal({ 
  isOpen, 
  onConfirm, 
  onCancel 
}: UnsavedChangesModalProps) {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* オーバーレイ */}
      <div className="fixed inset-0 bg-black bg-opacity-50" onClick={onCancel}></div>
      
      {/* モーダル本体 */}
      <div className="relative bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4 text-white">
        <div className="text-center">
          {/* タイトル */}
          <h2 className="text-lg font-medium mb-4">
            この画面を離れますか？
          </h2>
          
          {/* メッセージ */}
          <p className="text-gray-300 mb-6">
            行った変更は保存されません。
          </p>
          
          {/* ボタン */}
          <div className="flex flex-col gap-3">
            <button
              onClick={onConfirm}
              className="w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 rounded-md text-white font-medium transition-colors"
            >
              離れる
            </button>
            <button
              onClick={onCancel}
              className="w-full px-4 py-3 bg-gray-600 hover:bg-gray-500 rounded-md text-white font-medium transition-colors"
            >
              戻る
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
