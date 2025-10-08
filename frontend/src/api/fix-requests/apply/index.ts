/**
 * POST /api/fix-requests/apply
 */
export type Methods = {
  post: {
    reqBody: {
      token: string;
    };
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

