import type { BreadcrumbItem } from '@/api/categoryHelpers';

/**
 * ドキュメント詳細のレスポンス型
 */
export interface DocumentDetailResponse {
  entityId: number;
  title: string;
  description?: string;
  breadcrumbs?: BreadcrumbItem[];
}

/**
 * GET /api/document-entities/:entityId
 * PUT /api/document-entities/:entityId
 * DELETE /api/document-entities/:entityId
 */
export type Methods = {
  get: {
    resBody: {
      document: DocumentDetailResponse;
    };
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

