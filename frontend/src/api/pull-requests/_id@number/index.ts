import type { PullRequestDetailResponse } from '../../_pullRequest';

/**
 * GET /api/pull-requests/:id
 */
export type Methods = {
  get: {
    resBody: PullRequestDetailResponse;
  };
};

