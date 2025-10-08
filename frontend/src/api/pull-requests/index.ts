import type {
  CreatePullRequestRequest,
  CreatePullRequestResponse,
} from '../_pullRequest';

/**
 * GET /api/pull-requests
 * POST /api/pull-requests
 */
export type Methods = {
  get: {
    query?: {
      status?: string;
    };
    resBody: {
      pull_requests: Array<{
        id: number;
        title: string;
        description?: string;
        status: string;
        created_at: string;
        updated_at: string;
        author_nickname?: string;
      }>;
    };
  };
  post: {
    reqBody: CreatePullRequestRequest;
    resBody: CreatePullRequestResponse;
  };
};

