/**
 * GET /api/user-branches/diff
 */
export type Methods = {
  get: {
    query: {
      user_branch_id: number;
    };
    resBody: {
      diff: any[];
      user_branch_id?: number;
      organization_id?: number;
    };
  };
};

