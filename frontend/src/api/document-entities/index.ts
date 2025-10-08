/**
 * POST /api/document-entities
 */
export type Methods = {
  post: {
    reqBody: {
      title: string;
      body: string;
      sidebar_label?: string;
      category_entity_id: number;
    };
    resBody: {
      success: boolean;
      document_id?: number;
      message?: string;
    };
  };
};

