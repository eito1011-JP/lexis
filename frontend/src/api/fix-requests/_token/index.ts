/**
 * GET /api/fix-requests/:token
 */
export type Methods = {
  get: {
    query?: {
      pull_request_id?: string | number;
    };
    resBody: {
      current_pr: any;
      fix_request: any;
      status?: string;
    };
  };
};

