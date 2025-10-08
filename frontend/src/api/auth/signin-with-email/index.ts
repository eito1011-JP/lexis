/**
 * POST /api/auth/signin-with-email
 */
export type Methods = {
  post: {
    reqBody: {
      email: string;
      password: string;
    };
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

