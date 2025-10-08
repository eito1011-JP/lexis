/**
 * POST /api/activity-logs
 */
export type Methods = {
  post: {
    reqBody: {
      pull_request_id: string | number;
      action: string;
    };
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

