import { apiClient } from '../components/admin/api/client';
import { API_CONFIG } from '../components/admin/api/config';

// カテゴリ詳細の型定義
export interface CategoryDetail {
  id: number;
  title: string;
  description?: string;
  slug: string;
  sidebar_label: string;
  created_at: string;
  updated_at: string;
}

// カテゴリ詳細レスポンスの型定義
export interface CategoryDetailResponse {
  category: CategoryDetail;
}

// パンクズリストアイテムの型定義
export interface BreadcrumbItem {
  id: number;
  title: string;
}

// APIレスポンスの型定義（実際のAPIレスポンス構造に合わせる）
export interface ApiCategoryDetailResponse {
  id: number;
  title: string;
  description?: string;
  breadcrumbs?: BreadcrumbItem[];
}

/**
 * カテゴリ詳細を取得する
 */
export const fetchCategoryDetail = async (
  categoryId: string | number
): Promise<ApiCategoryDetailResponse> => {
  try {
    const response = await apiClient.get(`${API_CONFIG.ENDPOINTS.CATEGORIES.GET_DETAIL}/${categoryId}`);
    // APIレスポンスから category オブジェクトを取得
    return response.category;
  } catch (error: any) {
    console.error('カテゴリ詳細取得エラー:', error);
    throw error;
  }
};
