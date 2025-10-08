/**
 * GET /api/auth/me
 */
export type Methods = {
  get: {
    resBody: {
      user: {
        id: number;
        email: string;
        name?: string;
      };
    };
  };
};

