/**
 * Pull Request Reviewers API
 */

export type Methods = {
  get: {
    query?: {
      email?: string;
    };
    resBody: {
      users: Array<{
        id: number;
        email: string;
        nickname?: string;
        role?: string;
      }>;
    };
  };
  post: {
    reqBody: {
      pull_request_id: number;
      emails?: string[];
      reviewer_ids?: number[];
    };
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};
