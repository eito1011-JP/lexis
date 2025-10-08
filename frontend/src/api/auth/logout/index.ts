/**
 * POST /api/auth/logout
 */
export type Methods = {
  post: {
    reqBody: Record<string, never>;
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

