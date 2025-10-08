/**
 * PATCH /api/pull-requests/:id/close
 */
export type Methods = {
  patch: {
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

