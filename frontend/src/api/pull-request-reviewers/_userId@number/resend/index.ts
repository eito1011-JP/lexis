/**
 * PATCH /api/pull-request-reviewers/:userId/resend
 */
export type Methods = {
  patch: {
    reqBody: {
      action: string;
      pull_request_id: number;
      user_id: number;
    };
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

