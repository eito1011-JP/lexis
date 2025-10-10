/**
 * POST /api/auth/pre-users
 */
export type Methods = {
  post: {
    reqBody: {
      password: string;
      email: string;
    };
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

