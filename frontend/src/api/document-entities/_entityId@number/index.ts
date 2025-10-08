import type { BreadcrumbItem } from '@/api/categoryHelpers';

/**
 * ドキュメント詳細のレスポンス型
 */
export interface DocumentDetailResponse {
  id: number;
  title: string;
  description?: string;
  category?: {
    id: number;
    title: string;
  } | null;
  breadcrumbs?: BreadcrumbItem[];
}

/**
 * GET /api/document-entities/:entityId
 * PUT /api/document-entities/:entityId
 * DELETE /api/document-entities/:entityId
 */
export type Methods = {
  get: {
    resBody: DocumentDetailResponse;
  };
  put: {
    reqBody: {
      title: string;
      body: string;
      sidebar_label?: string;
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

