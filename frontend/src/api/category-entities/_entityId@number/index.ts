import type { BreadcrumbItem } from '@/api/categoryHelpers';

/**
 * カテゴリ詳細のレスポンス型
 */
export interface CategoryDetailResponse {
  id: number;
  entity_id: number;
  title: string;
  description?: string;
  slug: string;
  sidebar_label: string;
  breadcrumbs?: BreadcrumbItem[];
  created_at: string;
  updated_at: string;
}

/**
 * GET /api/category-entities/:entityId
 * PUT /api/category-entities/:entityId
 * DELETE /api/category-entities/:entityId
 */
export type Methods = {
  get: {
    resBody: {
      category: CategoryDetailResponse;
    };
  };
  put: {
    reqBody: {
      title: string;
      description?: string;
    };
    resBody: {
      success: boolean;
      message?: string;
    };
  };
  delete: {
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

