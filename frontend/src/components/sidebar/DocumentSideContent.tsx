import React, { useState, useEffect } from 'react';
import { ArrowDown } from '@/components/icon/common/ArrowDown';
import { Folder } from '@/components/icon/common/Folder';
import { ThreeDots } from '@/components/icon/common/ThreeDots';
import { Plus } from '@/components/icon/common/Plus';
import { Edit } from '@/components/icon/common/Edit';
import { apiClient } from '@/components/admin/api/client';

// APIから取得するカテゴリデータの型定義
interface ApiCategoryData {
  id: number;
  title: string;
}

// フロントエンドで使用するカテゴリの型定義
interface CategoryItem {
  id: number;
  label: string;
  icon?: React.ComponentType<{ className?: string }>;
  children?: DocumentItem[];
}

// ドキュメントの型定義
interface DocumentItem {
  id: number;
  label: string;
  icon?: React.ComponentType<{ className?: string }>;
}

// サイドコンテンツのプロパティ
interface DocumentSideContentProps {
  onCategorySelect?: (categoryId: number) => void;
  selectedCategoryId?: number;
}

// カテゴリデータを取得するサービス関数
const fetchCategories = async (parentId: number | null = null): Promise<ApiCategoryData[]> => {
  try {
    const params = new URLSearchParams();
    if (parentId !== null) {
      params.append('parent_id', parentId.toString());
    }
    
    const endpoint = `/api/document-categories${params.toString() ? `?${params.toString()}` : ''}`;
    const response = await apiClient.get(endpoint);
    
    return response.categories || [];
  } catch (error) {
    console.error('カテゴリの取得に失敗しました:', error);
    throw error;
  }
};

/**
 * ドキュメント用サイドコンテンツコンポーネント
 * 株式会社Nexis配下の階層構造のみを表示
 */
export default function DocumentSideContent({ onCategorySelect, selectedCategoryId }: DocumentSideContentProps) {
  // デフォルトで人事制度カテゴリを展開
  const [expandedItems, setExpandedItems] = useState<Set<number>>(new Set([4]));
  // ホバー状態を管理
  const [hoveredItem, setHoveredItem] = useState<number | null>(null);
  // カテゴリデータの状態管理
  const [categories, setCategories] = useState<CategoryItem[]>([]);
  // ローディング状態
  const [isLoading, setIsLoading] = useState<boolean>(true);
  // エラー状態
  const [error, setError] = useState<string | null>(null);

  // APIデータをCategoryItem形式に変換する関数
  const transformApiDataToCategories = (apiData: ApiCategoryData[]): CategoryItem[] => {
    return apiData.map(item => ({
      id: item.id,
      label: item.title,
      icon: Folder,
      children: [] // 現時点ではドキュメント子要素は取得しない
    }));
  };

  // カテゴリデータを取得するuseEffect
  useEffect(() => {
    const loadCategories = async () => {
      try {
        setIsLoading(true);
        setError(null);
        
        // parent_id=nullでルートカテゴリを取得
        const apiCategories = await fetchCategories(null);
        const transformedCategories = transformApiDataToCategories(apiCategories);
        
        setCategories(transformedCategories);
      } catch (err) {
        console.error('カテゴリの取得に失敗しました:', err);
        setError('カテゴリの取得に失敗しました');
        // エラー時はハードコーディングされたデータをフォールバックとして使用
        setCategories(fallbackCategories);
      } finally {
        setIsLoading(false);
      }
    };

    loadCategories();
  }, []);

  // フォールバック用のハードコーディングされたカテゴリ構造
  const fallbackCategories: CategoryItem[] = [
    {
      id: 1,
      label: 'ビジョン・ミッション',
      icon: Folder,
    },
    {
      id: 2,
      label: '会社制度',
      icon: Folder,
    },
    {
      id: 3,
      label: '事業内容',
      icon: Folder,
    },
    {
      id: 4,
      label: '人事制度',
      icon: Folder,
      children: [
        {
          id: 1,
          label: '有給の取得方法に...',
        },
        {
          id: 2,
          label: '昇進基準について',
        },
      ],
    },
  ];

  // カテゴリの展開/折りたたみを切り替え
  const toggleExpanded = (categoryId: number) => {
    const newExpanded = new Set(expandedItems);
    if (newExpanded.has(categoryId)) {
      newExpanded.delete(categoryId);
    } else {
      newExpanded.add(categoryId);
    }
    setExpandedItems(newExpanded);
  };

  // カテゴリ選択のハンドラ
  const handleCategoryClick = (categoryId: number) => {
    if (onCategorySelect) {
      onCategorySelect(categoryId);
    }
  };

  // 新規カテゴリ作成のハンドラ
  const handleCreateCategory = (parentCategoryId: number) => {
    const url = `/categories/${parentCategoryId}/create`;
    window.location.href = url;
  };

  // ルートカテゴリ作成のハンドラ（parent_id = null）
  const handleCreateRootCategory = () => {
    const url = `/categories/create`;
    window.location.href = url;
  };

  // ドキュメントアイテムをレンダリング
  const renderDocumentItem = (document: DocumentItem, level: number = 0) => {
    const isSelected = selectedCategoryId === document.id;
    const isHovered = hoveredItem === document.id;

    return (
      <div key={document.id} className="select-none">
        <div
          className={`flex items-center py-1.5 px-2 cursor-pointer hover:bg-gray-800 rounded transition-colors group ${
            isSelected ? 'bg-gray-800 text-white' : 'text-gray-300 hover:text-white'
          }`}
          style={{ paddingLeft: `${level * 4}px` }}
          onClick={() => handleCategoryClick(document.id)}
          onMouseEnter={() => setHoveredItem(document.id)}
          onMouseLeave={() => setHoveredItem(null)}
        >
          {/* ドキュメントには矢印なし、スペースのみ */}
          <div className="flex-shrink-0 w-6 h-6"></div>
          
          {/* ドキュメントラベル */}
          <span className="text-sm truncate flex-1">{document.label}</span>
          
          {/* 編集アイコン（ホバー時または選択時に表示） */}
          {(isHovered || isSelected) && (
            <div className="flex items-center ml-2 opacity-0 group-hover:opacity-100 transition-opacity">
              <button
                className="p-1 hover:bg-gray-700 rounded transition-colors"
                onClick={(e) => {
                  e.stopPropagation();
                  // 編集アクションをここに実装
                }}
              >
                <Edit className="w-3 h-3" />
              </button>
            </div>
          )}
        </div>
      </div>
    );
  };

  // カテゴリアイテムを再帰的にレンダリング
  const renderCategoryItem = (item: CategoryItem, level: number = 0) => {
    const isExpanded = expandedItems.has(item.id);
    const hasChildren = item.children && item.children.length > 0;
    const isSelected = selectedCategoryId === item.id;
    const isHovered = hoveredItem === item.id;
    const IconComponent = item.icon || Folder;

    return (
      <div key={item.id} className="select-none">
        <div
          className={`flex items-center py-1.5 px-2 cursor-pointer hover:bg-gray-800 rounded transition-colors group ${
            isSelected ? 'bg-gray-800 text-white' : 'text-gray-300 hover:text-white'
          }`}
          style={{ paddingLeft: `${8 + level * 20}px` }}
          onClick={() => handleCategoryClick(item.id)}
          onMouseEnter={() => setHoveredItem(item.id)}
          onMouseLeave={() => setHoveredItem(null)}
        >
          {/* 左端の矢印アイコン（全てのカテゴリに表示） */}
          {hasChildren ? (
            <button
              className="mr-0.5 flex-shrink-0 p-1 hover:bg-gray-700 rounded transition-transform"
              onClick={(e) => {
                e.stopPropagation();
                toggleExpanded(item.id);
              }}
            >
              <ArrowDown 
                className={`w-4 h-4 transition-transform duration-200 ${
                  isExpanded ? 'rotate-0' : '-rotate-90'
                }`} 
              />
            </button>
          ) : (
            <button
              className="mr-0.5 flex-shrink-0 p-1 hover:bg-gray-700 rounded transition-transform"
              onClick={(e) => {
                e.stopPropagation();
                toggleExpanded(item.id);
              }}
            >
              <ArrowDown 
                className={`w-4 h-4 transition-transform duration-200 ${
                  isExpanded ? 'rotate-0' : '-rotate-90'
                }`} 
              />
            </button>
          )}
          
          {/* カテゴリアイコン */}
          <IconComponent className="w-5 h-5 mr-1 flex-shrink-0" />
          
          {/* カテゴリラベル */}
          <span className="text-sm truncate flex-1">{item.label}</span>
          
          {/* 三点マークとプラスボタン（ホバー時または選択時に表示） */}
          {(isHovered || isSelected) && (
            <div className="flex items-center ml-2 opacity-0 group-hover:opacity-100 transition-opacity">
              <button
                className="p-1 hover:bg-gray-700 rounded transition-colors mr-1"
                onClick={(e) => {
                  e.stopPropagation();
                  // 三点メニューアクションをここに実装
                }}
              >
                <ThreeDots className="w-4 h-4" />
              </button>
              <button
                className="p-1 hover:bg-gray-700 rounded transition-colors"
                onClick={(e) => {
                  e.stopPropagation();
                  handleCreateCategory(item.id);
                }}
              >
                <Plus className="w-3 h-3" />
              </button>
            </div>
          )}
        </div>

        {/* 子ドキュメント */}
        {hasChildren && isExpanded && (
          <div className="ml-2">
            {item.children!.map((document) => renderDocumentItem(document, level + 1))}
          </div>
        )}

      </div>
    );
  };

  return (
    <div className="bg-[#0A0A0A] overflow-y-auto">
      {/* サイドコンテンツヘッダー */}
      <div className="px-4 py-1 group">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-semibold text-white">株式会社Nexis (main)</h3>
          {/* 会社名横のプラスボタン */}
          <button
            className="p-1 hover:bg-gray-700 rounded transition-colors opacity-0 group-hover:opacity-100"
            onClick={handleCreateRootCategory}
            title="新しいカテゴリを作成"
          >
            <Plus className="w-3 h-3 text-gray-300 hover:text-white" />
          </button>
        </div>
      </div>

      {/* カテゴリリスト */}
      <div className="">
        {isLoading ? (
          <div className="px-4 py-2 text-gray-400 text-sm">
            カテゴリを読み込み中...
          </div>
        ) : error ? (
          <div className="px-4 py-2 text-red-400 text-sm">
            {error}
          </div>
        ) : (
          categories.map((category) => renderCategoryItem(category))
        )}
      </div>
    </div>
  );
}
