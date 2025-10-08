import type { ActivityLog } from '../../../_pullRequest';

/**
 * GET /api/pull-requests/:id/activity-log-on-pull-request
 */
export type Methods = {
  get: {
    resBody: ActivityLog[];
  };
};

