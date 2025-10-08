/**
 * POST /api/organizations
 */
export type Methods = {
  post: {
    reqBody: {
      name: string;
      domain?: string;
    };
    resBody: {
      success: boolean;
      organization_id?: number;
      message?: string;
    };
  };
};

