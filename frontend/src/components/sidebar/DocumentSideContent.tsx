import React, { useState } from 'react';
import { ChevronDown } from '@/components/icon/common/ChevronDown';
import { ChevronRight } from '@/components/icon/common/ChevronRight';
import { Folder } from '@/components/icon/common/Folder';

// カテゴリの型定義
interface CategoryItem {
  id: string;
  label: string;
  icon?: React.ComponentType<{ className?: string }>;
  children?: CategoryItem[];
}

// サイドコンテンツのプロパティ
interface DocumentSideContentProps {
  onCategorySelect?: (categoryId: string) => void;
  selectedCategoryId?: string;
}

/**
 * ドキュメント用サイドコンテンツコンポーネント
 * 株式会社Nexis配下の階層構造のみを表示
 */
export default function DocumentSideContent({ onCategorySelect, selectedCategoryId }: DocumentSideContentProps) {
  // デフォルトで人事制度カテゴリを展開
  const [expandedItems, setExpandedItems] = useState<Set<string>>(new Set(['hr-system']));

  // 株式会社Nexis配下のカテゴリ構造
  const companyCategories: CategoryItem[] = [
    {
      id: 'vision-mission',
      label: 'ビジョン・ミッション',
      icon: Folder,
    },
    {
      id: 'company-system',
      label: '会社制度',
      icon: Folder,
    },
    {
      id: 'business-content',
      label: '事業内容',
      icon: Folder,
    },
    {
      id: 'hr-system',
      label: '人事制度',
      icon: Folder,
      children: [
        {
          id: 'benefits-method',
          label: '有給の取得方法に...',
          icon: Folder,
        },
        {
          id: 'promotion-criteria',
          label: '昇進基準について',
          icon: Folder,
        },
      ],
    },
  ];

  // カテゴリの展開/折りたたみを切り替え
  const toggleExpanded = (categoryId: string) => {
    const newExpanded = new Set(expandedItems);
    if (newExpanded.has(categoryId)) {
      newExpanded.delete(categoryId);
    } else {
      newExpanded.add(categoryId);
    }
    setExpandedItems(newExpanded);
  };

  // カテゴリ選択のハンドラ
  const handleCategoryClick = (categoryId: string) => {
    if (onCategorySelect) {
      onCategorySelect(categoryId);
    }
  };

  // カテゴリアイテムを再帰的にレンダリング
  const renderCategoryItem = (item: CategoryItem, level: number = 0) => {
    const isExpanded = expandedItems.has(item.id);
    const hasChildren = item.children && item.children.length > 0;
    const isSelected = selectedCategoryId === item.id;
    const IconComponent = item.icon || Folder;

    return (
      <div key={item.id} className="select-none">
        <div
          className={`flex items-center py-1.5 px-2 cursor-pointer hover:bg-gray-800 rounded transition-colors ${
            isSelected ? 'bg-gray-800 text-white' : 'text-gray-300 hover:text-white'
          }`}
          style={{ paddingLeft: `${8 + level * 20}px` }}
          onClick={() => handleCategoryClick(item.id)}
        >
          {/* 展開/折りたたみアイコン */}
          {hasChildren && (
            <button
              className="mr-1 p-1 hover:bg-gray-700 rounded"
              onClick={(e) => {
                e.stopPropagation();
                toggleExpanded(item.id);
              }}
            >
              {isExpanded ? (
                <ChevronDown className="w-3 h-3" />
              ) : (
                <ChevronRight className="w-3 h-3" />
              )}
            </button>
          )}
          
          {/* カテゴリアイコン */}
          <IconComponent className="w-4 h-4 mr-2 flex-shrink-0" />
          
          {/* カテゴリラベル */}
          <span className="text-sm truncate">{item.label}</span>
        </div>

        {/* 子カテゴリ */}
        {hasChildren && isExpanded && (
          <div className="ml-2">
            {item.children!.map((child) => renderCategoryItem(child, level + 1))}
          </div>
        )}
      </div>
    );
  };

  return (
    <div className="bg-[#0A0A0A] overflow-y-auto">
      {/* サイドコンテンツヘッダー */}
      <div className="p-4 border-b border-gray-700">
        <h3 className="text-sm font-semibold text-white">株式会社Nexis (main)</h3>
      </div>

      {/* カテゴリリスト */}
      <div className="p-2">
        {companyCategories.map((category) => renderCategoryItem(category))}
      </div>
    </div>
  );
}
