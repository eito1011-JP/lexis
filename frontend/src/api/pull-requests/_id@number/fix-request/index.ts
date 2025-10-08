/**
 * POST /api/pull-requests/:id/fix-request
 */
export type Methods = {
  post: {
    reqBody: {
      title: string;
      description: string;
      document_versions: any[];
    };
    resBody: {
      success: boolean;
      fix_request_token?: string;
      message?: string;
    };
  };
};

