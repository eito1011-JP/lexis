import React, { useEffect, useState } from 'react';
import { Folder } from '@/components/icon/common/Folder';
import { Document } from '@/components/icon/common/Document';

interface CreateActionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onCreateDocument: () => void;
  onCreateCategory: () => void;
  buttonRef?: React.RefObject<HTMLButtonElement>;
}

/**
 * ドキュメント作成とカテゴリ作成のアクションモーダル
 */
export default function CreateActionModal({ 
  isOpen, 
  onClose, 
  onCreateDocument, 
  onCreateCategory, 
  buttonRef 
}: CreateActionModalProps) {
  const [modalPosition, setModalPosition] = useState({ top: 0, left: 0 });

  useEffect(() => {
    if (isOpen && buttonRef?.current) {
      const buttonRect = buttonRef.current.getBoundingClientRect();
      setModalPosition({
        top: buttonRect.bottom + 8,
        left: buttonRect.left
      });
    }
  }, [isOpen, buttonRef]);

  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };

    const handleClickOutside = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      if (!target.closest('.create-action-modal') && !target.closest('button')) {
        onClose();
      }
    };

    if (isOpen) {
      document.addEventListener('keydown', handleEscape);
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('keydown', handleEscape);
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <>
      {/* オーバーレイ */}
      <div className="fixed inset-0 z-40" />
      
      {/* モーダル */}
      <div
        className="create-action-modal fixed z-50 bg-gray-800 border border-gray-600 rounded-lg shadow-lg py-2 min-w-[200px]"
        style={{
          top: `${modalPosition.top}px`,
          left: `${modalPosition.left}px`
        }}
      >
        {/* ドキュメント作成ボタン */}
        <button
          className="w-full px-4 py-3 text-left text-sm text-gray-200 hover:bg-gray-700 transition-colors flex items-center"
          onClick={() => {
            onCreateDocument();
            onClose();
          }}
        >
          <Document className="w-4 h-4 mr-3 text-gray-400" />
          <span>ドキュメントを作成</span>
        </button>

        {/* カテゴリ作成ボタン */}
        <button
          className="w-full px-4 py-3 text-left text-sm text-gray-200 hover:bg-gray-700 transition-colors flex items-center"
          onClick={() => {
            onCreateCategory();
            onClose();
          }}
        >
          <Folder className="w-4 h-4 mr-3 text-gray-400" />
          <span>カテゴリを作成</span>
        </button>
      </div>
    </>
  );
}
