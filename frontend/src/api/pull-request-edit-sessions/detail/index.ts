/**
 * GET /api/pull-request-edit-sessions/detail
 */
export type Methods = {
  get: {
    query: {
      token: string;
    };
    resBody: {
      session: any;
      pull_request: any;
      diffs: any[];
    };
  };
};

