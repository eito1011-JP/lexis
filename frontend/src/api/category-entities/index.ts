/**
 * カテゴリエンティティ一覧のレスポンス型
 */
export interface CategoryListItem {
  id: number;
  entity_id: number;
  title: string;
  parent_entity_id?: number | null;
}

/**
 * GET /api/category-entities
 * POST /api/category-entities
 */
export type Methods = {
  get: {
    query?: {
      parent_entity_id?: number | null;
    };
    resBody: {
      categories: CategoryListItem[];
    };
  };
  post: {
    reqBody: {
      title: string;
      description?: string;
      parent_entity_id?: number | null;
    };
    resBody: {
      success: boolean;
      category?: {
        id: number;
        entity_id: number;
        title: string;
      };
      message?: string;
    };
  };
};
