import React, { useRef, useEffect } from 'react';
import { Edit } from '@/components/icon/common/Edit';

interface CategoryActionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onEdit: () => void;
  onDelete: () => void;
  categoryName: string;
  buttonRef?: React.RefObject<HTMLButtonElement>;
}

/**
 * カテゴリアクションドロップダウンコンポーネント
 * ThreeDotsボタンから表示される編集・削除のドロップダウンメニュー
 */
export default function CategoryActionModal({ 
  isOpen, 
  onClose, 
  onEdit, 
  onDelete,
  categoryName,
  buttonRef
}: CategoryActionModalProps) {
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        onClose();
      }
    };

    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  // ボタンの位置を計算
  const getDropdownPosition = () => {
    if (!buttonRef?.current) return {};
    
    const buttonRect = buttonRef.current.getBoundingClientRect();
    
    // +ボタンの位置を基準にして、モーダルの右端を合わせる
    // +ボタンは三点ボタンの右隣にあるので、その位置を計算
    const plusButtonOffset = 32; // +ボタンの幅 + マージン
    const dropdownWidth = 140; // ドロップダウンの幅
    
    return {
      position: 'fixed' as const,
      top: buttonRect.bottom + 4,
      left: buttonRect.right + plusButtonOffset - dropdownWidth,
      zIndex: 1000,
    };
  };

  return (
    <div 
      ref={dropdownRef}
      style={getDropdownPosition()}
      className="bg-gray-800 rounded-lg border border-gray-600 shadow-lg overflow-hidden min-w-[140px]"
    >
      {/* タイトル */}
      <div className="px-4 py-3 text-center border-b border-gray-600">
        <span className="text-white text-sm font-medium">{categoryName}</span>
      </div>
      
      {/* メニューアイテム */}
      <div className="py-1">
        <button
          onClick={onEdit}
          className="w-full px-4 py-2 text-left text-white hover:bg-gray-700 transition-colors flex items-center gap-2 text-sm"
        >
          <Edit className="w-4 h-4" />
          編集
        </button>
        <button
          onClick={onDelete}
          className="w-full px-4 py-2 text-left text-red-400 hover:bg-gray-700 transition-colors flex items-center gap-2 text-sm"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
          削除
        </button>
      </div>
    </div>
  );
}
