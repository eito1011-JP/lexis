import { client } from './client';

// パンクズリストアイテムの型定義
export interface BreadcrumbItem {
  id: number;
  title: string;
}

// カテゴリ詳細レスポンスの型定義（バックエンドAPIと一致）
export interface CategoryDetailResponse {
  category: {
    id: number;
    entity_id: number;
    title: string;
    description?: string;
    breadcrumbs?: BreadcrumbItem[];
    created_at: string;
    updated_at: string;
  };
}

/**
 * カテゴリ詳細を取得する
 */
export const fetchCategoryDetail = async (
  categoryId: number
): Promise<CategoryDetailResponse['category']> => {
  try {
    const response = await client.category_entities._entityId(categoryId).$get();
    return response.category;
  } catch (error: any) {
    console.error('カテゴリ詳細取得エラー:', error);
    throw error;
  }
};
