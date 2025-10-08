import type { ConflictDiffResponse } from '../../../../_pullRequest';

/**
 * GET /api/pull-requests/:id/conflict/diff
 */
export type Methods = {
  get: {
    resBody: ConflictDiffResponse;
  };
};

