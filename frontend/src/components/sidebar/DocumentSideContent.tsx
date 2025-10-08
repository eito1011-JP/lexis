import React, { useState, useEffect, useRef } from 'react';
import { ArrowDown } from '@/components/icon/common/ArrowDown';
import { Folder } from '@/components/icon/common/Folder';
import { ThreeDots } from '@/components/icon/common/ThreeDots';
import { Plus } from '@/components/icon/common/Plus';
import { Edit } from '@/components/icon/common/Edit';
import { client } from '@/api/client';
import CategoryActionModal from '@/components/admin/CategoryActionModal';
import CreateActionModal from '@/components/sidebar/CreateActionModal';
import DocumentDeleteModal from '@/components/sidebar/DocumentDeleteModal';
import { useNavigate } from 'react-router-dom';
import { useUserMe } from '@/hooks/useUserMe';
import { useCategories } from '@/hooks/useCategories';
import { useToast } from '@/contexts/ToastContext';

// 共通のアイテム型定義
interface BaseItem {
  id: number;
  entityId: number;
  label: string;
  icon?: React.ComponentType<{ className?: string }>;
  type: 'category' | 'document';
}

// フロントエンドで使用するカテゴリの型定義
interface CategoryItem extends BaseItem {
  type: 'category';
  children?: BaseItem[];
}

// ドキュメントの型定義
interface DocumentItem extends BaseItem {
  type: 'document';
}

// サイドコンテンツのプロパティ
interface DocumentSideContentProps {
  onCategorySelect?: (categoryId: number) => void;
  onDocumentSelect?: (documentId: number) => void;
  selectedCategoryEntityId?: number;
  selectedDocumentEntityId?: number;
  onRefreshRef?: React.MutableRefObject<(() => Promise<void>) | null>;
}


/**
 * ドキュメント用サイドコンテンツコンポーネント
 * 株式会社Nexis配下の階層構造を無制限表示
 */
export default function DocumentSideContent({ onCategorySelect, onDocumentSelect, selectedCategoryEntityId, selectedDocumentEntityId, onRefreshRef }: DocumentSideContentProps) {
  const navigate = useNavigate();
  const { user, organization, activeUserBranch } = useUserMe();
  const toast = useToast();
  // useCategories フックでルートカテゴリを取得
  const { categories: rootCategories, isLoading: isLoadingCategories, isError, mutate: mutateCategories } = useCategories(null);
  const [expandedItems, setExpandedItems] = useState<Set<number>>(new Set([4]));
  // ホバー状態を管理
  const [hoveredItem, setHoveredItem] = useState<number | null>(null);
  // カテゴリデータの状態管理（階層構造を持つ形式に変換したもの）
  const [categories, setCategories] = useState<CategoryItem[]>([]);
  // モーダル状態
  const [showActionModal, setShowActionModal] = useState<boolean>(false);
  const [selectedCategory, setSelectedCategory] = useState<CategoryItem | null>(null);
  const [activeButtonRef, setActiveButtonRef] = useState<React.RefObject<HTMLButtonElement> | undefined>(undefined);
  // 作成アクションモーダル状態
  const [showCreateModal, setShowCreateModal] = useState<boolean>(false);
  const [createTargetCategoryId, setCreateTargetCategoryId] = useState<number | null>(null);
  const [createModalButtonRef, setCreateModalButtonRef] = useState<React.RefObject<HTMLButtonElement> | undefined>(undefined);
  // ドキュメント削除モーダル状態
  const [showDeleteModal, setShowDeleteModal] = useState<boolean>(false);
  const [selectedDocument, setSelectedDocument] = useState<DocumentItem | null>(null);

  console.log('categories', categories);
  // APIデータをCategoryItem形式に変換する関数
  const transformApiDataToCategories = (apiData: any[]): CategoryItem[] => {
    return apiData.map(item => ({
      id: item.id,
      entityId: item.entity_id,
      label: item.title,
      icon: Folder,
      type: 'category' as const,
      children: [] // 現時点ではドキュメント子要素は取得しない
    }));
  };

  // 深い階層まで再帰的に検索してカテゴリを更新する関数
  const updateCategoryInTree = (categories: CategoryItem[], targetEntityId: number, children: BaseItem[]): CategoryItem[] => {
    return categories.map(category => {
      if (category.entityId === targetEntityId) {
        return {
          ...category,
          children: children
        };
      }
      
      if (category.children && category.children.length > 0) {
        // 子要素のうちカテゴリタイプのもののみを再帰的に処理
        const childCategories = category.children.filter(child => child.type === 'category') as CategoryItem[];
        const updatedChildCategories = updateCategoryInTree(childCategories, targetEntityId, children);
        const childDocuments = category.children.filter(child => child.type === 'document');
        
        return {
          ...category,
          children: [...updatedChildCategories, ...childDocuments]
        };
      }
      
      return category;
    });
  };

  // 従属するカテゴリとドキュメントを取得する関数（無制限表示・深い階層対応）
  const handleFetchBelogingItems = async (categoryEntityId: number) => {
    try {
      const response = await client.nodes.$get({ 
        query: { category_entity_id: categoryEntityId }
      });
      
      // 既存のカテゴリデータを更新（深い階層まで対応）
      setCategories(prevCategories => {
        // 取得したすべてのカテゴリとドキュメントをchildrenに追加（制限なし）
        const categoryChildren: CategoryItem[] = (response.categories || [])
          .filter((cat: any) => cat && cat.id && cat.entity_id && cat.title) // null/undefined要素をフィルタ
          .map((cat: any) => ({
            id: cat.id,
            entityId: cat.entity_id,
            label: cat.title,
            icon: Folder,
            type: 'category' as const,
            children: [] // 子カテゴリには空の配列を初期化
          }));
        
        const documentChildren: BaseItem[] = (response.documents || [])
          .filter((doc: any) => doc && doc.id && doc.entity_id && (doc.title || doc.sidebar_label)) // null/undefined要素をフィルタ
          .map((doc: any) => ({
            id: doc.id,
            entityId: doc.entity_id,
            label: doc.sidebar_label || doc.title,
            type: 'document' as const,
          }));
        
        // すべてのカテゴリとドキュメントを結合（制限なし）
        const children = [...categoryChildren, ...documentChildren];
        
        // 深い階層まで再帰的に検索して更新
        // categoryEntityIdが有効であることを確認
        if (typeof categoryEntityId === 'number' && categoryEntityId > 0) {
          return updateCategoryInTree(prevCategories, categoryEntityId, children);
        }
        return prevCategories;
      });
      
    } catch (error) {
      console.error('従属アイテムの取得に失敗しました:', error);
      // エラーハンドリング - ユーザーに通知するかログのみにするか検討
    }
  };

  // rootCategoriesが変更されたときにcategoriesを更新
  useEffect(() => {
    if (rootCategories && rootCategories.length > 0) {
      const transformedCategories = transformApiDataToCategories(rootCategories);
      setCategories(transformedCategories);
    }
  }, [rootCategories]);

  // 親コンポーネントから呼び出せるrefresh関数を設定
  useEffect(() => {
    if (onRefreshRef) {
      onRefreshRef.current = async () => {
        // 展開されているカテゴリのリストを保存
        const expandedList = Array.from(expandedItems);
        
        // カテゴリ一覧を再取得（mutateは新しいデータを返す）
        const newData = await mutateCategories();
        
        // 新しいデータが取得できた場合、categoriesを更新
        if (newData && newData.categories) {
          const newTransformedCategories = newData.categories.map((item: any) => ({
            id: item.id,
            entityId: item.entity_id,
            label: item.title,
            icon: Folder,
            type: 'category' as const,
            children: []
          }));
          setCategories(newTransformedCategories);
          
          // 展開されているカテゴリの子要素を順次再読み込み
          for (const entityId of expandedList) {
            try {
              const response = await client.nodes.$get({ 
                query: { category_entity_id: entityId }
              });
              
              // 取得したデータでカテゴリツリーを更新
              setCategories(prevCategories => {
                const categoryChildren = (response.categories || [])
                  .filter((cat: any) => cat && cat.id && cat.entity_id && cat.title)
                  .map((cat: any) => ({
                    id: cat.id,
                    entityId: cat.entity_id,
                    label: cat.title,
                    icon: Folder,
                    type: 'category' as const,
                    children: []
                  }));
                
                const documentChildren = (response.documents || [])
                  .filter((doc: any) => doc && doc.id && doc.entity_id && (doc.title || doc.sidebar_label))
                  .map((doc: any) => ({
                    id: doc.id,
                    entityId: doc.entity_id,
                    label: doc.sidebar_label || doc.title,
                    type: 'document' as const,
                  }));
                
                const children = [...categoryChildren, ...documentChildren];
                
                // 深い階層まで再帰的に検索して更新
                const updateTree = (categories: CategoryItem[]): CategoryItem[] => {
                  return categories.map(category => {
                    if (category.entityId === entityId) {
                      return {
                        ...category,
                        children: children
                      };
                    }
                    
                    if (category.children && category.children.length > 0) {
                      const childCategories = category.children.filter(child => child.type === 'category') as CategoryItem[];
                      const updatedChildCategories = updateTree(childCategories);
                      const childDocuments = category.children.filter(child => child.type === 'document');
                      
                      return {
                        ...category,
                        children: [...updatedChildCategories, ...childDocuments]
                      };
                    }
                    
                    return category;
                  });
                };
                
                return updateTree(prevCategories);
              });
            } catch (error) {
              console.error('従属アイテムの取得に失敗しました:', error);
            }
          }
        }
      };
    }
  }, [mutateCategories, expandedItems, onRefreshRef]);

  // カテゴリの展開/折りたたみを切り替え
  const toggleExpanded = (categoryEntityId: number) => {
    const newExpanded = new Set(expandedItems);
    if (newExpanded.has(categoryEntityId)) {
      newExpanded.delete(categoryEntityId);
    } else {
      newExpanded.add(categoryEntityId);
      // フォルダ展開時に従属アイテムを取得
      handleFetchBelogingItems(categoryEntityId);
    }
    setExpandedItems(newExpanded);
  };

  // カテゴリ選択のハンドラ
  const handleCategoryClick = (categoryId: number) => {
    if (onCategorySelect) {
      onCategorySelect(categoryId);
    }
  };

  // Plusボタンクリック時のハンドラ（モーダルを表示）
  const handlePlusClick = (parentCategoryEntityId: number, event: React.MouseEvent<HTMLButtonElement>) => {
    setCreateTargetCategoryId(parentCategoryEntityId);
    setCreateModalButtonRef({ current: event.currentTarget });
    setShowCreateModal(true);
  };

  // ドキュメント作成のハンドラ
  const handleCreateDocument = () => {
    if (createTargetCategoryId) {
      // createTargetCategoryIdはentityIdなので、そのまま使用
      const url = `/categories/${createTargetCategoryId}/documents/create`;
      window.location.href = url;
    }
  };

  // カテゴリ作成のハンドラ
  const handleCreateCategory = () => {
    if (createTargetCategoryId) {
      // createTargetCategoryIdはentityIdなので、そのまま使用
      const url = `/categories/${createTargetCategoryId}/create`;
      window.location.href = url;
    }
  };

  // 作成モーダルを閉じるハンドラ
  const handleCloseCreateModal = () => {
    setShowCreateModal(false);
    setCreateTargetCategoryId(null);
    setCreateModalButtonRef(undefined);
  };

  // ルートカテゴリ作成のハンドラ（parent_entity_id = null）
  const handleCreateRootCategory = () => {
    const url = `/categories/create`;
    window.location.href = url;
  };

  // 三点メニューのハンドラ
  const handleThreeDotsClick = (category: CategoryItem, event: React.MouseEvent<HTMLButtonElement>) => {
    setSelectedCategory(category);
    setActiveButtonRef({ current: event.currentTarget });
    setShowActionModal(true);
  };

  // モーダルを閉じるハンドラ
  const handleCloseModal = () => {
    setShowActionModal(false);
    setSelectedCategory(null);
    setActiveButtonRef(undefined);
  };

  // 編集のハンドラ
  const handleEdit = () => {
    if (selectedCategory) {
      // selectedCategory.idはdocument_categories.idなので、そのまま使用
      const url = `/categories/${selectedCategory.entityId}/edit`;
      window.location.href = url;
    }
    handleCloseModal();
  };

  // 削除のハンドラ
  const handleDelete = async () => {
    if (selectedCategory) {
      try {
        await client.category_entities._entityId(selectedCategory.entityId).$delete();

        toast.show({
          message: 'カテゴリが削除されました',
          type: 'success'
        });

        // カテゴリ一覧を再取得し、結果を待つ
        const newData = await mutateCategories();
        
        // 新しいデータが取得できた場合、categoriesを即座に更新
        if (newData && newData.categories) {
          const newTransformedCategories = transformApiDataToCategories(newData.categories);
          setCategories(newTransformedCategories);
        }

      } catch (error) {
        console.error('カテゴリの削除に失敗しました:', error);
        toast.show({
          message: 'カテゴリの削除に失敗しました',
          type: 'error'
        });
      }
    }
    handleCloseModal();
  };

  // ドキュメント選択ハンドラ
  const handleDocumentClick = (documentEntityId: number) => {
    if (onDocumentSelect) {
      onDocumentSelect(documentEntityId);
    }
  };

  // ドキュメント編集ハンドラ
  const handleDocumentEdit = (documentId: number, categoryEntityId: number) => {
    // categoryEntityIdはdocument_category_entities.idなので、
    // document_categories.idに変換する必要がある場合は別途処理が必要
    const url = `/categories/${categoryEntityId}/documents/${documentId}/edit`;
    navigate(url);
  };

  // ドキュメント削除モーダルを開くハンドラ
  const handleDocumentDeleteClick = (document: DocumentItem) => {
    setSelectedDocument(document);
    setShowDeleteModal(true);
  };

  // ドキュメント削除モーダルを閉じるハンドラ
  const handleCloseDeleteModal = () => {
    setShowDeleteModal(false);
    setSelectedDocument(null);
  };

  // ドキュメント削除実行ハンドラ
  const handleDocumentDelete = async () => {
    if (!selectedDocument) return;

    try {
      await client.document_entities._entityId(selectedDocument.entityId).$delete();
      
      // 削除後にUIを更新
      mutateCategories();
      
      // 展開されているカテゴリの子要素も再読み込み
      for (const entityId of expandedItems) {
        await handleFetchBelogingItems(entityId);
      }
      
    } catch (error) {
      console.error('ドキュメントの削除に失敗しました:', error);
      // TODO: エラートーストの表示
    }
  };

  // ドキュメントアイテムをレンダリング
  const renderDocumentItem = (document: DocumentItem, level: number = 0, categoryEntityId?: number) => {
    const isSelected = selectedDocumentEntityId === document.entityId;
    const isHovered = hoveredItem === document.id;

    return (
      <div key={document.id} className="select-none">
        <div
          className={`flex items-center py-1.5 px-2 cursor-pointer hover:bg-gray-800 rounded transition-colors group ${
            isSelected ? 'bg-gray-800 text-white' : 'text-gray-300 hover:text-white'
          }`}
          style={{ paddingLeft: `${level * 0.8}rem` }}
          onClick={() => handleDocumentClick(document.entityId)}
          onMouseEnter={() => setHoveredItem(document.id)}
          onMouseLeave={() => setHoveredItem(null)}
        >
          {/* ドキュメントには矢印なし、スペースのみ */}
          <div className="flex-shrink-0 w-6 h-6"></div>
          
          {/* ドキュメントラベル */}
          <span className="text-sm truncate flex-1">{document.label}</span>
          
          {/* 削除・編集アイコン（ホバー時または選択時に表示） */}
          {(isHovered || isSelected) && (
            <div className="flex items-center ml-2 opacity-0 group-hover:opacity-100 transition-opacity">
              <button
                className="p-1 hover:bg-gray-700 rounded transition-colors mr-1"
                onClick={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  handleDocumentDeleteClick(document);
                }}
                title="削除"
              >
                <svg className="w-3 h-3 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
              <button
                className="p-1 hover:bg-gray-700 rounded transition-colors"
                onClick={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  if (categoryEntityId) {
                    handleDocumentEdit(document.entityId, categoryEntityId);
                  }
                }}
                title="編集"
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
    const isExpanded = expandedItems.has(item.entityId);
    const hasChildren = item.children && item.children.length > 0;
    const isSelected = selectedCategoryEntityId === item.entityId;
    const isHovered = hoveredItem === item.entityId;
    const IconComponent = item.icon || Folder;

    return (
      <div key={item.entityId} className="select-none">
        <div
          className={`flex items-center py-1.5 px-2 cursor-pointer hover:bg-gray-800 rounded transition-colors group ${
            isSelected ? 'bg-gray-800 text-white' : 'text-gray-300 hover:text-white'
          }`}
          style={{ paddingLeft: `${1 + level * 0.8}rem` }}
          onClick={() => handleCategoryClick(item.entityId)}
          onMouseEnter={() => setHoveredItem(item.entityId)}
          onMouseLeave={() => setHoveredItem(null)}
        >
          {/* 左端の矢印アイコン（全てのカテゴリに表示） */}
          <button
            className="mr-0.5 flex-shrink-0 p-1 hover:bg-gray-700 rounded transition-transform"
            onClick={(e) => {
              e.preventDefault();
              e.stopPropagation();
              toggleExpanded(item.entityId);
            }}
          >
            <ArrowDown 
              className={`w-4 h-4 transition-transform duration-200 ${
                isExpanded ? 'rotate-0' : '-rotate-90'
              }`} 
            />
          </button>
          
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
                  e.preventDefault();
                  e.stopPropagation();
                  handleThreeDotsClick(item, e);
                }}
              >
                <ThreeDots className="w-4 h-4" />
              </button>
              <button
                className="p-1 hover:bg-gray-700 rounded transition-colors"
                onClick={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  handlePlusClick(item.entityId, e);
                }}
              >
                <Plus className="w-3 h-3" />
              </button>
            </div>
          )}
        </div>

        {/* 子要素（カテゴリとドキュメント） */}
        {hasChildren && isExpanded && (
          <div className="ml-2">
            {item.children!.map((child) => 
              child.type === 'category' 
                ? renderCategoryItem(child as CategoryItem, level + 1)
                : renderDocumentItem(child as DocumentItem, level + 1, item.entityId)
            )}
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
          <h3 className="text-sm font-semibold">
            <span className="text-[#B1B1B1]">{organization?.name || '読み込み中...'}</span> 
            <span className="text-[#FF9900]">（{activeUserBranch ? `${user?.nickname}のversion` : '最新版'}）</span>
          </h3>
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
        {isLoadingCategories ? (
          <div className="px-4 py-2 text-gray-400 text-sm">
            カテゴリを読み込み中...
          </div>
        ) : isError ? (
          <div className="px-4 py-2 text-red-400 text-sm">
            カテゴリの取得に失敗しました
          </div>
        ) : (
          categories.map((category) => renderCategoryItem(category))
        )}
      </div>

      {/* カテゴリアクションモーダル */}
      <CategoryActionModal
        isOpen={showActionModal}
        onClose={handleCloseModal}
        onEdit={handleEdit}
        onDelete={handleDelete}
        categoryName={selectedCategory?.label || ''}
        buttonRef={activeButtonRef}
      />

      {/* 作成アクションモーダル */}
      <CreateActionModal
        isOpen={showCreateModal}
        onClose={handleCloseCreateModal}
        onCreateDocument={handleCreateDocument}
        onCreateCategory={handleCreateCategory}
        buttonRef={createModalButtonRef}
      />

      {/* ドキュメント削除モーダル */}
      <DocumentDeleteModal
        isOpen={showDeleteModal}
        onClose={handleCloseDeleteModal}
        onDelete={handleDocumentDelete}
        documentName={selectedDocument?.label || ''}
      />
    </div>
  );
}
