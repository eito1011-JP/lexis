/**
 * PUT /api/pull-requests/:id/merge
 */
export type Methods = {
  put: {
    reqBody: {
      pull_request_id: string | number;
    };
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

