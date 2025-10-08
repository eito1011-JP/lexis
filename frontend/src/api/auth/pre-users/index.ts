/**
 * POST /api/auth/pre-users
 */
export type Methods = {
  post: {
    reqBody: {
      token: string;
      password: string;
      nickname: string;
    };
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

