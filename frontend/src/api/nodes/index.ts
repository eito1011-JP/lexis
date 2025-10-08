/**
 * GET /api/nodes
 */
export type Methods = {
  get: {
    query: {
      category_entity_id: number;
    };
    resBody: {
      categories: Array<{
        id: number;
        entity_id: number;
        title: string;
        slug: string;
        sidebar_label: string;
      }>;
      documents: Array<{
        id: number;
        entity_id: number;
        title: string;
        sidebar_label: string;
      }>;
    };
  };
};

